<?php

namespace Webkul\CryptoCallCenter\DataGrids\Overrides;

use Webkul\Admin\DataGrids\Contact\OrganizationDataGrid as BaseDataGrid;
use Webkul\CryptoCallCenter\DataGrids\Concerns\HidesUnauthorizedMassActions;

/**
 * The organization datagrid with its mass actions limited to what the role may run.
 *
 * Organizations are read-only for a call center operator, so its "Delete" mass
 * action never reaches the screen.
 */
class OrganizationDataGrid extends BaseDataGrid
{
    use HidesUnauthorizedMassActions;

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        parent::prepareMassActions();

        $this->removeUnauthorizedMassActions();
    }
}
