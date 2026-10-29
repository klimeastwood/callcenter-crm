<?php

namespace Webkul\CryptoCallCenter\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\CryptoCallCenter\Models\WithdrawalRequest;
use Webkul\CryptoCallCenter\Support\TradingEventPresenter;
use Webkul\DataGrid\DataGrid;

/**
 * The payout queue of the call center.
 *
 * The grid reads the tables of the trading application and never writes them:
 * the decision is taken on the request page, where the approve and reject forms
 * sit behind the same `withdrawals.card` key, so the row action is only the way
 * into that page - the queue itself cannot approve anything by accident.
 *
 * The list is not scoped by `bouncer()->getAuthorizedUserIds()` on purpose. That
 * scope answers "whose leads may I see", and a payout is a client's money, not
 * an employee's record: a request has no CRM owner to compare with. What the
 * operator may see is decided by the ACL key alone, so the queue is granted
 * deliberately (Settings → Roles) rather than inherited from the lead scope.
 * The queue is masked either way: the number the money is paid to is opened on
 * the request page, behind `withdrawals.card`.
 */
class WithdrawalDataGrid extends DataGrid
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
        $queryBuilder = DB::table('withdrawal_requests')
            ->addSelect(
                'withdrawal_requests.id',
                'withdrawal_requests.user_id',
                'withdrawal_requests.withdrawal_card_id',
                'withdrawal_requests.amount_usd',
                'withdrawal_requests.method',
                'withdrawal_requests.wallet_address',
                'withdrawal_requests.status',
                'withdrawal_requests.is_approved',
                'withdrawal_requests.reviewed_at',
                'withdrawal_requests.created_at',
                'users.name as client_name',
                'users.email as client_email',
                'withdrawal_cards.last4 as card_last4',
                'withdrawal_cards.brand as card_brand',
                'withdrawal_cards.holder_name as card_holder',
            )
            ->leftJoin('users', 'withdrawal_requests.user_id', '=', 'users.id')
            ->leftJoin('withdrawal_cards', 'withdrawal_requests.withdrawal_card_id', '=', 'withdrawal_cards.id');

        $this->addFilter('id', 'withdrawal_requests.id');
        $this->addFilter('created_at', 'withdrawal_requests.created_at');
        $this->addFilter('client_name', 'users.name');
        $this->addFilter('client_email', 'users.email');
        $this->addFilter('amount', 'withdrawal_requests.amount_usd');
        $this->addFilter('method', 'withdrawal_requests.method');
        $this->addFilter('status', 'withdrawal_requests.status');

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('crypto_call_center::app.withdrawals.index.datagrid.id'),
            'type' => 'integer',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'created_at',
            'label' => trans('crypto_call_center::app.withdrawals.index.datagrid.created-at'),
            'type' => 'date',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'date_range',
            'closure' => fn ($row) => core()->formatDate($row->created_at, 'd.m.Y H:i'),
        ]);

        $this->addColumn([
            'index' => 'client_name',
            'label' => trans('crypto_call_center::app.withdrawals.index.datagrid.client'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'client_email',
            'label' => trans('crypto_call_center::app.withdrawals.index.datagrid.email'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'amount',
            'label' => trans('crypto_call_center::app.withdrawals.index.datagrid.amount'),
            'type' => 'string',
            'searchable' => false,
            'sortable' => true,
            'filterable' => true,
            'closure' => fn ($row) => '$'.TradingEventPresenter::money($row->amount_usd),
        ]);

        $this->addColumn([
            'index' => 'method',
            'label' => trans('crypto_call_center::app.withdrawals.index.datagrid.method'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => array_map(
                fn ($method) => [
                    'label' => trans('crypto_call_center::app.withdrawals.methods.'.$method),
                    'value' => $method,
                ],
                WithdrawalRequest::METHODS
            ),
            'closure' => fn ($row) => e(trans('crypto_call_center::app.withdrawals.methods.'.$row->method)),
        ]);

        /**
         * The requisites column is what the payout desk reads while the money
         * leaves: the masked number of the card or the wallet the client named,
         * never a full number. It is not searchable on purpose - a LIKE over a
         * card number would write the number into the query log.
         */
        $this->addColumn([
            'index' => 'requisites',
            'label' => trans('crypto_call_center::app.withdrawals.index.datagrid.requisites'),
            'type' => 'string',
            'searchable' => false,
            'sortable' => false,
            'filterable' => false,
            'closure' => fn ($row) => view('crypto_call_center::withdrawals.datagrid.requisites', ['row' => $row])->render(),
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('crypto_call_center::app.withdrawals.index.datagrid.status'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => array_map(
                fn ($status) => [
                    'label' => trans('crypto_call_center::app.withdrawals.status.'.$status),
                    'value' => $status,
                ],
                WithdrawalRequest::STATUSES
            ),
            'closure' => fn ($row) => view('crypto_call_center::withdrawals.datagrid.status', ['row' => $row])->render(),
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        /**
         * Opening the request is the reveal: the page repeats the payout details
         * and, for a card, the number the desk has to read out. It is therefore
         * a second permission, not a side effect of seeing the queue.
         */
        if (! bouncer()->hasPermission('withdrawals.card')) {
            return;
        }

        $this->addAction([
            'index' => 'view',
            'icon' => 'icon-eye',
            'title' => trans('crypto_call_center::app.withdrawals.index.datagrid.open'),
            'method' => 'GET',
            'url' => fn ($row) => route('admin.crypto_call_center.withdrawals.card', $row->id),
        ]);
    }
}
