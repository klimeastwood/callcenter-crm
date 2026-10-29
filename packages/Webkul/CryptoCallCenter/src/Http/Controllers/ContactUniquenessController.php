<?php

namespace Webkul\CryptoCallCenter\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;

/**
 * Answers the "is this contact detail already taken" question asked while a
 * contact is typed into the lead form.
 *
 * Krayin asks its own `admin.settings.attributes.check_unique_validation`
 * endpoint. That route carries no ACL entry of its own and therefore inherits
 * the nearest mapped feature, `admin.settings.*`: an operator who may create a
 * lead but has no Settings permission is answered with 401. The Vue component
 * turns every failed request into "Error validating email. Please try again.",
 * which is the error the operator sees under a perfectly valid address.
 *
 * The package answers the same question on a route named under `admin.leads.*`
 * instead. The core Bouncer authorizes an unmapped route against its nearest
 * mapped ancestor feature (the mechanism it documents for `admin.leads.search`),
 * so the check needs the lead feature the form itself already requires and
 * nothing more. An operator never reaches a permission it does not have, and an
 * account without any lead permission still gets the 401 it deserves.
 *
 * The lookup is also narrower than the core one: the attribute is resolved by
 * code *and* entity type. A code alone is ambiguous - `contact_numbers` exists
 * for persons and for warehouses - and the core endpoint could compare a person
 * phone against a warehouse attribute, which never matches.
 */
class ContactUniquenessController extends Controller
{
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
    ) {}

    /**
     * Report whether the submitted value is still free for the entity type.
     *
     * Only the two person attributes the lead form sends are accepted. The
     * endpoint answers a single bit about a value the caller already typed, so
     * an allow-list keeps it from becoming a probe for arbitrary attributes.
     */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', 'string', 'in:persons'],
            'attribute_code' => ['required', 'string', 'in:emails,contact_numbers'],
            'attribute_value' => ['required', 'string', 'max:255'],

            /**
             * The lead form sends the id of the person being edited and nothing
             * at all for a person that is about to be created.
             */
            'entity_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $attribute = $this->attributeRepository->findOneWhere([
            'code' => $data['attribute_code'],
            'entity_type' => $data['entity_type'],
        ]);

        if (! $attribute) {
            /**
             * A diagnostic for a deployment whose attributes are missing, not a
             * line a user reads: the component logs a refused check instead of
             * printing it.
             */
            return response()->json([
                'message' => 'The attribute is not configured for this entity type.',
            ], 422);
        }

        return response()->json([
            'validated' => $this->attributeValueRepository->isValueUnique(
                /**
                 * `isValueUnique()` excludes the id it is given, and a missing
                 * id would compare `entity_id != NULL`, which matches no row at
                 * all: every address would look free. Id 0 matches no row
                 * either, but it does it by being a value no entity carries, so
                 * a new person is compared against the whole contact book.
                 */
                $data['entity_id'] ?? 0,
                $data['entity_type'],
                $attribute,
                $data['attribute_value'],
            ),
        ]);
    }
}
