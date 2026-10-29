<?php

namespace Webkul\CryptoCallCenter\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\CryptoCallCenter\Models\CallLog;
use Webkul\DataGrid\DataGrid;
use Webkul\User\Repositories\UserRepository;

class CallLogDataGrid extends DataGrid
{
    /**
     * The screen is a call history, so the newest conversation comes first.
     */
    protected $sortColumn = 'call_date';

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('call_logs')
            ->addSelect(
                'call_logs.id',
                'call_logs.phone_number',
                'call_logs.duration',
                'call_logs.status',
                'call_logs.recording_url',
                'call_logs.transcript',
                'call_logs.call_date',
                'leads.id as lead_id',
                'leads.title as lead_title',
                'users.id as user_id',
                'users.name as user_name',
            )
            ->leftJoin('leads', 'call_logs.lead_id', '=', 'leads.id')
            ->leftJoin('users', 'call_logs.user_id', '=', 'users.id');

        /**
         * A manager listens to their own calls unless the role's data scope
         * grants more: `global` returns null (no limit), `group` returns the
         * colleagues of their groups, `individual` only the acting user.
         */
        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $queryBuilder->whereIn('call_logs.user_id', $userIds);
        }

        $this->addFilter('id', 'call_logs.id');
        $this->addFilter('call_date', 'call_logs.call_date');
        $this->addFilter('phone_number', 'call_logs.phone_number');
        $this->addFilter('lead_title', 'leads.title');
        $this->addFilter('user_name', 'users.name');
        $this->addFilter('duration', 'call_logs.duration');
        $this->addFilter('status', 'call_logs.status');
        $this->addFilter('recording_url', 'call_logs.recording_url');

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('crypto_call_center::app.call-logs.index.datagrid.id'),
            'type' => 'integer',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'call_date',
            'label' => trans('crypto_call_center::app.call-logs.index.datagrid.call-date'),
            'type' => 'date',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'date_range',
            'closure' => fn ($row) => core()->formatDate($row->call_date, 'd.m.Y H:i'),
        ]);

        $this->addColumn([
            'index' => 'phone_number',
            'label' => trans('crypto_call_center::app.call-logs.index.datagrid.phone-number'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'lead_title',
            'label' => trans('crypto_call_center::app.call-logs.index.datagrid.lead'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index' => 'user_name',
            'label' => trans('crypto_call_center::app.call-logs.index.datagrid.manager'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'searchable_dropdown',
            'filterable_options' => [
                'repository' => UserRepository::class,
                'column' => [
                    'label' => 'name',
                    'value' => 'name',
                ],
            ],
        ]);

        $this->addColumn([
            'index' => 'duration',
            'label' => trans('crypto_call_center::app.call-logs.index.datagrid.duration'),
            'type' => 'string',
            'searchable' => false,
            'sortable' => true,
            'filterable' => true,
            'closure' => fn ($row) => CallLog::formatDuration($row->duration),
        ]);

        $this->addColumn([
            'index' => 'status',
            'label' => trans('crypto_call_center::app.call-logs.index.datagrid.status'),
            'type' => 'string',
            'searchable' => true,
            'sortable' => true,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => array_map(
                fn ($status) => [
                    'label' => trans('crypto_call_center::app.call-logs.status.'.$status),
                    'value' => $status,
                ],
                CallLog::STATUSES
            ),
            'closure' => fn ($row) => view('crypto_call_center::call-logs.datagrid.status', ['row' => $row])->render(),
        ]);

        $this->addColumn([
            'index' => 'recording_url',
            'label' => trans('crypto_call_center::app.call-logs.index.datagrid.recording'),
            'type' => 'string',
            'searchable' => false,
            'sortable' => false,
            'filterable' => true,
            'closure' => fn ($row) => $row->recording_url
                ? '<a class="text-brandColor transition-all hover:underline" href="'.e($row->recording_url).'" target="_blank" rel="noopener noreferrer">'
                    .trans('crypto_call_center::app.call-logs.index.datagrid.listen').'</a>'
                : '--',
        ]);

        /**
         * The transcript stays hidden by default: QA opens it through the
         * column settings for a single call instead of reading it in every
         * row, yet it is still exported when the history is downloaded.
         */
        $this->addColumn([
            'index' => 'transcript',
            'label' => trans('crypto_call_center::app.call-logs.index.datagrid.transcript'),
            'type' => 'string',
            'searchable' => false,
            'sortable' => false,
            'filterable' => false,
            'visibility' => false,
            'closure' => fn ($row) => filled($row->transcript)
                ? e(Str::limit($row->transcript, 150))
                : '--',
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
                'title' => trans('crypto_call_center::app.call-logs.index.datagrid.view-lead'),
                'method' => 'GET',
                'url' => fn ($row) => route('admin.leads.view', $row->lead_id),
            ]);
        }
    }
}
