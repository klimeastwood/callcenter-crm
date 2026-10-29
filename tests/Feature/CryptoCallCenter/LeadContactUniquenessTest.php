<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * The lead form used to ask the *Settings* endpoint whether an address or a phone
 * number was already taken (`admin.settings.attributes.check_unique_validation`).
 *
 * That route declares no ACL entry, so the core Bouncer authorized it against the
 * nearest mapped feature, `admin.settings.*`. An operator holds no Settings
 * permission, was answered with 401, and the Vue component printed its catch-all
 * text under the field: "Error validating email. Please try again.", even for a
 * freshly typed, perfectly valid address. The same message then appeared under the
 * phone field, because its component reused the same catch block.
 *
 * The package now answers the question on `admin.leads.check_unique_contact`, a
 * route named under the lead feature, and the components no longer turn a failed
 * auxiliary lookup into a validation error. These tests pin both halves down: the
 * endpoint answers the operator, still refuses an account without the lead
 * permission, notices a taken value, and the lead form itself saves a person with
 * a valid email and phone number without a single validation error.
 */
uses(DatabaseTransactions::class);

/**
 * An account holding the packaged lead permission set.
 *
 * `--sync` is passed because a development database may already carry the role
 * with the permission list of an earlier release.
 */
function contactCheckOperator(): User
{
    Artisan::call('crypto-call-center:operator-role', ['--sync' => true]);

    $role = Role::query()
        ->where('name', config('crypto_call_center.roles.operator.name'))
        ->firstOrFail();

    return User::create([
        'name' => 'Operator '.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'individual',
        'role_id' => $role->id,
    ]);
}

/**
 * The query the email and phone components send, with an override per case.
 */
function contactCheckQuery(array $overrides = []): array
{
    return array_merge([
        'entity_type' => 'persons',
        'attribute_code' => 'emails',
        'attribute_value' => 'contact-check-'.uniqid().'@example.invalid',
    ], $overrides);
}

/**
 * A person already holding the address, created the way the contact form does.
 */
function contactCheckPersonWithEmail(User $owner, string $email): Person
{
    return app(PersonRepository::class)->create([
        'name' => 'Held '.uniqid(),
        'entity_type' => 'persons',
        'user_id' => $owner->id,
        'emails' => [['value' => $email, 'label' => 'work']],
    ]);
}

it('answers the contact check for an operator who may create leads', function () {
    /**
     * The attribute has to exist for the endpoint to resolve it; the parallel test
     * databases are migrated but not seeded, so the helper creates the missing row.
     */
    webFormAttributeFor('persons', 'emails');

    $operator = contactCheckOperator();

    $email = 'free-'.uniqid().'@example.invalid';

    $this->actingAs($operator, 'user')
        ->getJson(route('admin.leads.check_unique_contact', contactCheckQuery([
            'attribute_value' => $email,
        ])))
        ->assertOk()
        ->assertExactJson(['validated' => true]);
});

it('reports a taken address and excludes the person being edited', function () {
    webFormAttributeFor('persons', 'emails');

    $operator = contactCheckOperator();

    $email = 'taken-'.uniqid().'@example.invalid';

    $person = contactCheckPersonWithEmail($operator, $email);

    /**
     * A person that is about to be created sends no id at all, so the address is
     * compared with the whole contact book.
     */
    $this->actingAs($operator, 'user')
        ->getJson(route('admin.leads.check_unique_contact', contactCheckQuery([
            'attribute_value' => $email,
        ])))
        ->assertOk()
        ->assertExactJson(['validated' => false]);

    /**
     * Editing that very person sends its id, and the address it already carries is
     * not a duplicate of itself.
     */
    $this->actingAs($operator, 'user')
        ->getJson(route('admin.leads.check_unique_contact', contactCheckQuery([
            'attribute_value' => $email,
            'entity_id' => $person->id,
        ])))
        ->assertOk()
        ->assertExactJson(['validated' => true]);
});

it('refuses the contact check without a lead permission', function () {
    webFormAttributeFor('persons', 'emails');

    /**
     * The route names the lead feature on purpose rather than skipping the ACL: an
     * account that cannot open the lead form has no business reading the contact
     * book through it.
     */
    $role = Role::create([
        'name' => 'Contacts Without Leads '.uniqid(),
        'description' => 'Contacts only',
        'permission_type' => 'custom',
        'permissions' => ['contacts.persons'],
    ]);

    $user = User::create([
        'name' => 'Contact Only '.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'status' => 1,
        'view_permission' => 'global',
        'role_id' => $role->id,
    ]);

    $this->actingAs($user, 'user')
        ->getJson(route('admin.leads.check_unique_contact', contactCheckQuery()))
        ->assertStatus(401);
});

it('creates a lead whose person carries a valid email and phone', function () {
    $pipeline = securityTestPipeline();

    $operator = contactCheckOperator();

    /**
     * Both attributes are needed for the form rules to build: the parallel test
     * databases are migrated but not seeded.
     */
    webFormAttributeFor('persons', 'emails');
    webFormAttributeFor('persons', 'contact_numbers');

    $email = 'lead-'.uniqid().'@example.invalid';

    $phone = '+37529'.random_int(1000000, 9999999);

    $title = 'Contact Lead '.uniqid();

    $this->actingAs($operator, 'user')
        ->post(route('admin.leads.store'), [
            'title' => $title,
            'lead_value' => 500,
            'lead_pipeline_id' => $pipeline->id,
            'lead_pipeline_stage_id' => $pipeline->stages()->value('id'),
            'person' => [
                'name' => 'Van Contact',
                'emails' => [['value' => $email, 'label' => 'work']],
                'contact_numbers' => [['value' => $phone, 'label' => 'work']],
            ],
        ])
        /**
         * The reported bug was a validation error, and a failed validation still
         * redirects: the session is what tells the two apart.
         */
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $lead = Lead::query()->where('title', $title)->sole();

    $person = Person::query()->findOrFail($lead->person_id);

    expect($person->emails)->toHaveCount(1)
        ->and($person->emails[0]['value'])->toBe($email)
        ->and($person->contact_numbers)->toHaveCount(1)
        ->and($person->contact_numbers[0]['value'])->toBe($phone);

    /**
     * The saved person is what the check now reports as taken, so the contact book
     * and the endpoint agree on the stored shape.
     */
    $this->actingAs($operator, 'user')
        ->getJson(route('admin.leads.check_unique_contact', contactCheckQuery([
            'attribute_value' => $email,
        ])))
        ->assertOk()
        ->assertExactJson(['validated' => false]);
});
