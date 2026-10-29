<?php

namespace Webkul\CryptoCallCenter\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Webkul\Core\Eloquent\Repository;
use Webkul\CryptoCallCenter\Models\BalanceAdjustment;

/**
 * Read and write access to the audit trail of manual balance changes.
 */
class BalanceAdjustmentRepository extends Repository
{
    /**
     * The newest adjustments of one lead, for the lead card block.
     */
    public function latestForLead(int $leadId, int $limit = 10): Collection
    {
        return $this->getModel()
            ->newQuery()
            ->with('admin')
            ->where('lead_id', $leadId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return BalanceAdjustment::class;
    }
}
