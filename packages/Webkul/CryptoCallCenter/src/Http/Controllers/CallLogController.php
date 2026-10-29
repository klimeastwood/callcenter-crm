<?php

namespace Webkul\CryptoCallCenter\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Webkul\CryptoCallCenter\DataGrids\CallLogDataGrid;

class CallLogController extends Controller
{
    /**
     * Display the call history of the call center.
     *
     * The grid is scoped inside `CallLogDataGrid` by the role's data scope, so
     * a manager only sees the conversations they are allowed to review.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(CallLogDataGrid::class)->process();
        }

        return view('crypto_call_center::call-logs.index');
    }
}
