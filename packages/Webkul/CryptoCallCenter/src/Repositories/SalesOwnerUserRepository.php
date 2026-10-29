<?php

namespace Webkul\CryptoCallCenter\Repositories;

use Webkul\CryptoCallCenter\Models\SalesOwner;
use Webkul\User\Repositories\UserRepository;

/**
 * The user list the "Sales Owner" lookup reads.
 *
 * It is a `UserRepository` - the name matters, `AttributeRepository::getLookUpOptions()`
 * detects the users lookup by it and applies the `status = 1` and data-scope
 * rules every other user lookup gets - but its model carries a global scope that
 * drops the trading clients. The name also contains "UserRepository", which is
 * what that detection reads, so the special handling of a user lookup is kept.
 */
class SalesOwnerUserRepository extends UserRepository
{
    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return SalesOwner::class;
    }
}
