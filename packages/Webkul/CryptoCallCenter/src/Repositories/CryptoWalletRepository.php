<?php

namespace Webkul\CryptoCallCenter\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Webkul\Core\Eloquent\Repository;
use Webkul\CryptoCallCenter\Contracts\CryptoWallet;

class CryptoWalletRepository extends Repository
{
    /**
     * Wallets of a single lead.
     *
     * The relation is deliberately not added to the core Lead model:
     * `Webkul\Lead\Models\Lead` uses `Webkul\Attribute\Traits\CustomAttribute`,
     * whose `getAttribute()` resolves unknown keys as custom attributes, so a
     * relation registered with `resolveRelationUsing()` is never returned by
     * `$lead->cryptoWallets`. Querying through the repository keeps the core
     * model untouched and the result predictable.
     */
    public function findByLead(int $leadId): Collection
    {
        return $this->findWhere(['lead_id' => $leadId]);
    }

    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'wallet_address',
        'network',
        'lead_id',
    ];

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return CryptoWallet::class;
    }
}
