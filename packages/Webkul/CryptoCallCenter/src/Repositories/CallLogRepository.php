<?php

namespace Webkul\CryptoCallCenter\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Webkul\Core\Eloquent\Repository;
use Webkul\CryptoCallCenter\Contracts\CallLog;

class CallLogRepository extends Repository
{
    /**
     * Calls of a single lead, newest first.
     *
     * The relation is deliberately not added to the core Lead model:
     * `Webkul\Lead\Models\Lead` uses `Webkul\Attribute\Traits\CustomAttribute`,
     * whose `getAttribute()` resolves unknown keys as custom attributes, so a
     * relation registered with `resolveRelationUsing()` is never returned by
     * `$lead->callLogs`. Querying through the repository keeps the core model
     * untouched and the result predictable.
     */
    public function findByLead(int $leadId): Collection
    {
        return $this->getModel()
            ->newQuery()
            ->where('lead_id', $leadId)
            ->orderByDesc('call_date')
            ->get();
    }

    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'phone_number',
        'status',
        'lead_id',
        'user_id',
        'call_date',
    ];

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return CallLog::class;
    }
}
