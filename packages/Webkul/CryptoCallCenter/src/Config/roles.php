<?php

/**
 * Roles the package provisions on its own.
 *
 * A role listed here is only a curated subset of the ACL tree the Admin package
 * and this package already declare: no new permission key is invented, so no
 * core ACL entry has to be edited and a Krayin upgrade cannot silently widen
 * what the role may reach. A key that stops existing makes the provisioning
 * command fail loudly instead of creating a role with a dead permission.
 *
 * Keep `name` stable. It is the natural key the provisioning command looks the
 * role up by, so renaming it here creates a second role instead of updating the
 * role an administrator may already have assigned.
 *
 * A declaration may carry:
 *
 * - `name` / `description` - what the role is called and what it is for.
 * - `permissions`         - the ACL keys the role grants.
 * - `inherits`            - the key of another role whose definition is the
 *                           starting point: permissions are unioned, every other
 *                           key is overridden by the child. A supervising role
 *                           therefore cannot fall behind the role it supervises
 *                           when that role gains a screen.
 * - `view_permission`     - the data scope the accounts holding the role are
 *                           pinned to (`global`, `group`, `individual`). Leave
 *                           the key out, or set it to `null`, to keep the scope
 *                           a purely administrative choice.
 * - `supervisor`          - the role may change the balance of the leads it is
 *                           allowed to see, not only of the ones it owns.
 * - `assign_creator_as_lead_owner` - a lead the role creates belongs to the
 *                           account that created it, whoever the form named.
 */
return [
    'operator' => [
        'name' => 'Call Center Operator',

        'description' => 'Leads and activities: own only, create/view/edit. Contacts: the shared company book, create/view/edit. Wallets and call history: view. Withdrawals and deposits: the queues. Balance changes: own leads only.',

        /**
         * The data scope the role is pinned to.
         *
         * Krayin stores `view_permission` on the user, not on the role, so this
         * value is applied to every account holding the role: Settings -> Users
         * cannot widen an operator to `group` or `global`, and the lead list
         * keeps showing only the leads owned by the operator
         * (`leads.user_id` = the operator) through the core
         * `bouncer()->getAuthorizedUserIds()` filter. Existing accounts are
         * aligned by `php artisan crypto-call-center:operator-role --sync-users`.
         *
         * The contact book is the exception and is deliberately shared: the
         * package Bouncer returns no owner filter on the `admin.contacts.*`
         * routes for the packaged roles, so the scope above still governs leads,
         * activities and the balance while every operator can open a person or
         * an organization created by a colleague. The contact timeline stays
         * owner-scoped, because it carries the colleague's notes and calls. See
         * `Webkul\CryptoCallCenter\Bouncer`.
         *
         * Leave the key out to keep the scope a purely administrative choice.
         */
        'view_permission' => 'individual',

        /**
         * The operator works the pipeline end to end, so the lead it creates is
         * its own lead: the sales owner of a new lead is the account that typed
         * it in, never whoever the form happened to preselect. Without this the
         * operator would create a lead it cannot read back, because its scope
         * only shows the leads it owns.
         *
         * The assignment runs on `lead.create.after` (see
         * `Listeners/AssignLeadSalesOwner.php`), so a lead created by the
         * web-form, the API or an administrator keeps the owner it was given.
         */
        'assign_creator_as_lead_owner' => true,

        'permissions' => [
            /**
             * Leads - the group, the lead card, creating a lead and editing it.
             * Deleting is deliberately left out: an operator works the pipeline,
             * an administrator owns what enters it.
             *
             * `leads.create` opens the form, `leads.create.quick-create` is the
             * key the `admin.leads.store` route resolves to (the ACL maps a
             * route to the most specific key it declares, and
             * `leads.create.quick-create` sorts after `leads.create`), so the
             * two go together.
             */
            'leads',
            'leads.create',
            'leads.create.quick-create',
            'leads.view',
            'leads.edit',

            /**
             * Contacts - the group and both of its children, with the person
             * and organization cards readable and writable, so the operator can
             * fill in what the client says on the phone. `contacts` carries the
             * parent menu node (a node is rendered only when its own key is
             * granted); it maps to a screen that no longer exists, so it grants
             * nothing beyond the node itself.
             */
            'contacts',
            'contacts.persons',
            'contacts.persons.create',
            'contacts.persons.create.quick-create',
            'contacts.persons.view',
            'contacts.persons.edit',
            'contacts.organizations',
            'contacts.organizations.create',
            'contacts.organizations.create.quick-create',
            'contacts.organizations.edit',

            /**
             * Activities - the screen, the "create activity" action of the lead
             * card and the editing of a note the operator typed itself. Deleting
             * somebody else's log stays with its owner.
             */
            'activities',
            'activities.create',
            'activities.edit',

            /**
             * Crypto - the wallets screen and the balance block on the lead
             * card. `crypto_call_center.balance` only opens the block: the block
             * itself checks the ownership, so the key alone is not a licence to
             * move somebody else's money.
             */
            'crypto_call_center',
            'crypto_call_center.balance',

            /**
             * Call history - what the client did in the trading cabinet. An
             * operator reads it before dialling, so it is a read-only screen:
             * the data comes from the cabinet, not from here.
             */
            'call_logs',

            /**
             * Withdrawals - the payout queue of the trading cabinet. The list is
             * masked (a card shows its last four digits and a payout to a wallet
             * shows the address), so an operator can answer "where is my money".
             * `withdrawals.card` is what opens the full number and closes the
             * request, and stays with the supervisor who actually pays out.
             */
            'withdrawals',

            /**
             * Deposits - the queue of top-ups the clients staged. Reading it is
             * how an operator answers "did my money arrive"; approving one moves
             * the balance and therefore stays with the moderator
             * (`deposits.review`).
             */
            'deposits',
        ],
    ],

    'moderator' => [
        'name' => 'Call Center Moderator',

        'description' => 'Everything an operator reaches, plus the supervised desk: all leads, contacts and activities, the payout decision, the deposit review, and balance changes on those leads. No Settings, Users, Roles, Groups, Configuration, Products or Quotes.',

        /**
         * A moderator is an operator that supervises, so the definition starts
         * from the operator one and only adds to it: when the operator gains a
         * screen, the next provisioning run hands it to the moderator too.
         */
        'inherits' => 'operator',

        /**
         * The moderator sees the whole desk, whatever the settings say.
         *
         * The role is a supervising one: it approves payouts, reviews deposits
         * and moves balances across the desk, so a per-account `individual`
         * scope would silently hide most of the work. Pinning the role to
         * `global` is applied to every account holding it (`--sync-users`
         * repairs existing accounts), exactly as the operator is pinned to
         * `individual`; Settings -> Users cannot narrow it back.
         *
         * Leaving the key out would make the scope an administrative choice,
         * which is what the operator role did before it was pinned: an
         * administrator could submit `global` for an operator and the account
         * would read every lead. The moderator must not rely on that.
         */
        'view_permission' => 'global',

        /**
         * A moderator may adjust the balance of every lead on the desk, not only
         * of the ones it owns: that is what supervising a desk means. The role is
         * pinned to `global`, so the leads it covers are the whole company; the
         * balance endpoint still refuses a lead outside that scope, which keeps
         * the rule correct if the scope is ever narrowed.
         */
        'supervisor' => true,

        'permissions' => [
            /**
             * The payout desk. Revealing the whole number of a client card and
             * closing the request are one action: the moderator is the one who
             * approves, rejects and pays out, so the masked queue of the operator
             * is not enough.
             */
            'withdrawals.card',

            /**
             * The deposit review. Approving credits the trader balance and
             * rejecting closes the request, so both are desk actions: the
             * operator reads the queue, the moderator decides.
             */
            'deposits.review',
        ],
    ],
];
