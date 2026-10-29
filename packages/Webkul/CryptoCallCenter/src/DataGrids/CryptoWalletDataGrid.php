<?php

namespace Webkul\CryptoCallCenter\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class CryptoWalletDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('crypto_wallets')
            ->addSelect(
                'crypto_wallets.id',
                'crypto_wallets.wallet_address',
                'crypto_wallets.network',
                'crypto_wallets.created_at',
                'leads.id as lead_id',
                'leads.title as lead_title',
            )
            ->leftJoin('leads', 'crypto_wallets.lead_id', '=', 'leads.id');

        /**
         * Keep the records inside the acting user's data scope, the same way
         * the core grids limit leads to their authorized owners.
         */
        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $queryBuilder->whereIn('leads.user_id', $userIds);
        }

        $this->addFilter('id', 'crypto_wallets.id');
        $this->addFilter('wallet_address', 'crypto_wallets.wallet_address');
        $this->addFilter('network', 'crypto_wallets.network');
        $this->addFilter('lead_title', 'leads.title');
        $this->addFilter('created_at', 'crypto_wallets.created_at');

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('crypto_call_center::app.wallets.index.datagrid.id'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'wallet_address',
            'label' => trans('crypto_call_center::app.wallets.index.datagrid.wallet-address'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'network',
            'label' => trans('crypto_call_center::app.wallets.index.datagrid.network'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'lead_title',
            'label' => trans('crypto_call_center::app.wallets.index.datagrid.lead'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'created_at',
            'label' => trans('crypto_call_center::app.wallets.index.datagrid.created-at'),
            'type' => 'date',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'date_range',
            'closure' => fn ($row) => core()->formatDate($row->created_at),
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('leads.view')) {
            $this->addAction([
                'index' => 'view',
                'icon' => 'icon-eye',
                'title' => trans('crypto_call_center::app.wallets.index.datagrid.view-lead'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.leads.view', $row->lead_id),
            ]);
        }
    }
}
