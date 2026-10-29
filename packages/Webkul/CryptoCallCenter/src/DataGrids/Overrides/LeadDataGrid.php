<?php

namespace Webkul\CryptoCallCenter\DataGrids\Overrides;

use Webkul\Admin\DataGrids\Lead\LeadDataGrid as BaseDataGrid;
use Webkul\CryptoCallCenter\DataGrids\Concerns\HidesUnauthorizedMassActions;
use Webkul\CryptoCallCenter\Repositories\SalesOwnerUserRepository;

/**
 * The lead datagrid with its mass actions limited to what the role may run and
 * its "Sales Person" filter limited to the staff accounts.
 *
 * The core class is extended, not edited: the provider binds this class to the
 * name the controller already resolves, and everything the core grid declares -
 * columns, filters, row actions - is inherited untouched. Only the lookup of the
 * owner filter is replaced, because the users table also holds the clients of
 * the trading cabinet and a client cannot own a lead.
 */
class LeadDataGrid extends BaseDataGrid
{
    use HidesUnauthorizedMassActions;

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        parent::prepareColumns();

        /**
         * The filter is a searchable dropdown that asks the server for matches
         * (`admin.datagrid.look_up`), and the server resolves the options from
         * the repository named here - so naming the staff-only repository is
         * enough to keep the clients out of the list.
         */
        foreach ($this->getColumns() as $column) {
            if ($column->getIndex() !== 'sales_person') {
                continue;
            }

            $column->setFilterableOptions([
                'repository' => SalesOwnerUserRepository::class,
                'column' => [
                    'label' => 'name',
                    'value' => 'name',
                ],
            ]);
        }
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        parent::prepareMassActions();

        $this->removeUnauthorizedMassActions();
    }
}
