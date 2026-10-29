<?php

namespace Webkul\CryptoCallCenter\DataGrids\Overrides;

use Webkul\Admin\DataGrids\Activity\ActivityDataGrid as BaseDataGrid;
use Webkul\CryptoCallCenter\DataGrids\Concerns\HidesUnauthorizedMassActions;

/**
 * The activity datagrid with its mass actions limited to what the role may run.
 *
 * A call center operator may create activities but not edit or delete them, so
 * the screen keeps no mass action at all for that role.
 */
class ActivityDataGrid extends BaseDataGrid
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
