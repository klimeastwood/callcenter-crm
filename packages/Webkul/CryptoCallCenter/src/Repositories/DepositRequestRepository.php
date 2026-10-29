<?php

namespace Webkul\CryptoCallCenter\Repositories;

use Illuminate\Support\Facades\Schema;
use Webkul\Core\Eloquent\Repository;
use Webkul\CryptoCallCenter\Models\DepositRequest;

class DepositRequestRepository extends Repository
{
    /**
     * The deposit table the trading application writes.
     */
    public const REQUESTS_TABLE = 'deposit_requests';

    /**
     * The cards the deposits are matched against.
     */
    public const CARDS_TABLE = 'deposit_cards';

    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'user_id',
        'status',
    ];

    /**
     * Whether the trading application has deployed its deposit tables.
     *
     * The CRM ships without them - they belong to the trading side - so every
     * screen checks instead of failing on a missing table.
     */
    public static function available(): bool
    {
        return Schema::hasTable(self::REQUESTS_TABLE) && Schema::hasTable(self::CARDS_TABLE);
    }

    /**
     * One request with the card it points at, or null when unknown.
     */
    public function findWithCard(int $id): ?DepositRequest
    {
        if (! static::available()) {
            return null;
        }

        return $this->getModel()->query()
            ->with(['card', 'user', 'reviewer'])
            ->find($id);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return DepositRequest::class;
    }
}
