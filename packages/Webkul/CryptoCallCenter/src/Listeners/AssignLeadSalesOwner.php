<?php

namespace Webkul\CryptoCallCenter\Listeners;

use Webkul\CryptoCallCenter\Support\OperatorScope;
use Webkul\Lead\Models\Lead;

/**
 * Hands a lead the role creates to the account that created it.
 *
 * The operator scope only shows the leads an operator owns, so a lead typed in
 * for a colleague would disappear from the list the moment it is saved. The
 * declaration (`assign_creator_as_lead_owner` in `Config/roles.php`) closes that
 * hole for the roles that ask for it and leaves every other creator - an
 * administrator, the web-form, the API - with the owner it submitted.
 *
 * The write is quiet on purpose: it is part of creating the lead, not a second
 * edit of it, so it must not add a second timeline entry about the owner.
 */
class AssignLeadSalesOwner
{
    public function handle(Lead $lead): void
    {
        $creator = auth()->guard('user')->user();

        if (! OperatorScope::assignsCreatorAsLeadOwner($creator)) {
            return;
        }

        if ((int) $lead->user_id === (int) $creator->id) {
            return;
        }

        $lead->user_id = $creator->id;

        $lead->saveQuietly();
    }
}
