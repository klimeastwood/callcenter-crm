<?php

namespace Webkul\CryptoCallCenter\Repositories;

use Illuminate\Support\Facades\Schema;
use Webkul\Core\Eloquent\Repository;
use Webkul\CryptoCallCenter\Contracts\WithdrawalRequest as WithdrawalRequestContract;
use Webkul\CryptoCallCenter\Models\WithdrawalRequest;

class WithdrawalRequestRepository extends Repository
{
    /**
     * The payout table the trading application writes.
     */
    public const REQUESTS_TABLE = 'withdrawal_requests';

    /**
     * The cards the payouts are sent to.
     */
    public const CARDS_TABLE = 'withdrawal_cards';

    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'user_id',
        'status',
        'method',
    ];

    /**
     * Whether the trading application has deployed its payout tables.
     *
     * The CRM ships without them — they belong to the trading side — so every
     * screen and every command checks instead of failing on a missing table.
     */
    public static function available(): bool
    {
        return Schema::hasTable(self::REQUESTS_TABLE) && Schema::hasTable(self::CARDS_TABLE);
    }

    /**
     * One request with the card it should be paid to and the operator who
     * decided it, or null when unknown.
     *
     * The reviewer is loaded up front because the page prints it next to the
     * decision: a lazy load here would be one query per request row.
     */
    public function findWithCard(int $id): ?WithdrawalRequestContract
    {
        if (! static::available()) {
            return null;
        }

        return $this->getModel()->query()
            ->with(['card', 'reviewer'])
            ->find($id);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return WithdrawalRequest::class;
    }
}
