<?php

namespace Webkul\CryptoCallCenter;

use Webkul\Admin\Bouncer as CoreBouncer;
use Webkul\CryptoCallCenter\Support\OperatorScope;

/**
 * The contact book is shared, the pipeline is not.
 *
 * Krayin's data scope (`view_permission`) answers one question: whose records
 * may this account see. The package pins a call center operator to `individual`,
 * because an operator must not read a colleague's leads, activities or balance.
 * That same scope, however, also runs inside every contact controller
 * (`preventUnauthorizedAccess($person->user_id)`), so an operator could not open
 * a person or an organization created by somebody else. A call center needs the
 * opposite: the client who calls is picked from one common contact book, while
 * the work on that client stays private.
 *
 * The override therefore returns "no owner filter" for the contact book of the
 * packaged roles only. Every other route and every other role keeps the core
 * behaviour, so leads, activities, balance and the rest are still scoped to the
 * operator's own records, and no contact controller has to be copied to change
 * one check.
 *
 * The scope is dropped per request, not per model: outside the `admin.contacts.*`
 * routes the core Bouncer answers unchanged. That covers the Ajax grid, the
 * search endpoint used by the lead form, the person card and the organization
 * screens, which are the only places that read a contact owner.
 *
 * The contact timeline is the one deliberate exception: it carries the notes
 * and the correspondence of the record, so it stays owner-scoped even though
 * the card above it is shared.
 *
 * A moderator is pinned to `global`, so `getAuthorizedUserIds()` already returns
 * `null` for it and this override changes nothing there.
 */
class Bouncer extends CoreBouncer
{
    /**
     * A shared contact book is not owner-scoped.
     *
     * Returning `null` is the core Bouncer's own way of saying "no restriction".
     * The contact grids never asked for an owner filter, so the only readers of
     * this value on a contact screen are the authorization checks that would
     * otherwise hide a colleague's person or organization.
     *
     * @return array<int, int>|null
     */
    public function getAuthorizedUserIds(): ?array
    {
        if (! $this->sharesContactBook()) {
            return parent::getAuthorizedUserIds();
        }

        return null;
    }

    /**
     * Whether the current request reads the contact book on a packaged role.
     *
     * The timeline is excluded on purpose: the card is shared, the working log
     * on it is not, so the operator still sees only its own calls and notes.
     */
    protected function sharesContactBook(): bool
    {
        if (! request()->routeIs('admin.contacts.*')) {
            return false;
        }

        if (request()->routeIs('admin.contacts.persons.activities.*')) {
            return false;
        }

        return OperatorScope::roleOf(auth()->guard('user')->user()) !== null;
    }
}
