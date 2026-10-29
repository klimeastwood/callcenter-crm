<?php

namespace Webkul\CryptoCallCenter\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\CryptoCallCenter\Models\DepositRequest;
use Webkul\CryptoCallCenter\Support\TradingEventPresenter;
use Webkul\DataGrid\DataGrid;

/**
 * The deposit review queue of the call center.
 *
 * A deposit does not reach the balance until an operator confirms it, so this
 * grid is the operator's work list: the newest pending request first, the card
 * masked, and the review page - which opens the full number and offers the two
 * decisions - behind its own permission.
 *
 * The list is not scoped by `bouncer()->getAuthorizedUserIds()` on purpose. That
 * scope answers "whose leads may I see", and a deposit is a client's money, not
 * an employee's record: the row has no CRM owner to compare with. What the
 * operator may see is decided by the ACL key alone, so the queue is granted
 * deliberately (Settings → Roles) rather than inherited from the lead scope.
 */
class DepositDataGrid extends DataGrid
{
    /**
     * The newest request is the one that waits for a decision.
     */
    protected $sortColumn = 'created_at';

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('deposit_requests')
            ->addSelect(
                'deposit_requests.id',
                'deposit_requests.user_id',
                'deposit_requests.deposit_card_id',
                'deposit_requests.amount_usd',
                'deposit_requests.status',
                'deposit_requests.is_approved',
                'deposit_requests.reviewed_at',
                'deposit_requests.created_at',
                'users.name as client_name',
                'users.email as client_email',
                'deposit_cards.last4 as card_last4',
                'deposit_cards.brand as card_brand',
                'deposit_cards.holder_name as card_holder',
            )
            ->leftJoin('users', 'deposit_requests.user_id', '=', 'users.id')
            ->leftJoin('deposit_cards', 'deposit_requests.deposit_card_id', '=', 'deposit_cards.id');

        $this->addFilter('id', 'deposit_requests.id');
        $this->addFilter('created_at', 'deposit_requests.created_at');
        $this->addFilter('client_name', 'users.name');
        $this->addFilter('client_email', 'users.email');
        $this->addFilter('amount', 'deposit_requests.amount_usd');
        $this->addFilter('status', 'deposit_requests.status');

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('crypto_call_center::app.deposits.index.datagrid.id'),
            'type' => 'integer',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'created_at',
            'label' => trans('crypto_call_center::app.deposits.index.datagrid.created-at'),
            'type' => 'datetime',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'datetime_range',
        ]);

        $this->addColumn([
            'index' => 'client_name',
            'label' => trans('crypto_call_center::app.deposits.index.datagrid.client'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'client_email',
            'label' => trans('crypto_call_center::app.deposits.index.datagrid.email'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'amount',
            'label' => trans('crypto_call_center::app.deposits.index.datagrid.amount'),
            'type' => 'string',
            'searchable' => false,
            'sortable' => true,
            'filterable' => true,
            'closure' => fn ($row) => '$'.TradingEventPresenter::money($row->amount_usd),
        ]);

        /**
         * The card is what makes the deposit verifiable, but the queue only ever
         * prints the masked number: a LIKE over a card number would write the
         * number into the query log, so the column is not searchable.
         */
        $this->addColumn([
            'index' => 'requisites',
            'label' => trans('crypto_call_center::app.deposits.index.datagrid.requisites'),
            'type' => 'string',
            'searchable' => false,
            'sortable' => false,
            'filterable' => false,
            'closure' => fn ($row) => view('crypto_call_center::deposits.datagrid.requisites', ['row' => $row])->render(),
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('crypto_call_center::app.deposits.index.datagrid.status'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => array_map(
                fn ($status) => [
                    'label' => trans('crypto_call_center::app.deposits.status.'.$status),
                    'value' => $status,
                ],
                DepositRequest::STATUSES
            ),
            'closure' => fn ($row) => view('crypto_call_center::deposits.datagrid.status', ['row' => $row])->render(),
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        /**
         * Opening the request is the reveal: the page repeats the card number
         * and carries the approve and reject forms, so it is a permission of its
         * own rather than a side effect of seeing the queue.
         */
        if (! bouncer()->hasPermission('deposits.review')) {
            return;
        }

        $this->addAction([
            'index' => 'view',
            'icon' => 'icon-eye',
            'title' => trans('crypto_call_center::app.deposits.index.datagrid.open'),
            'method' => 'GET',
            'url' => fn ($row) => route('admin.crypto_call_center.deposits.show', $row->id),
        ]);
    }
}
