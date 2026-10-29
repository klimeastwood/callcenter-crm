<?php

namespace Webkul\CryptoCallCenter\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Webkul\CryptoCallCenter\DataGrids\CryptoWalletDataGrid;

class CryptoWalletController extends Controller
{
    /**
     * Display a listing of the lead crypto wallets.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(CryptoWalletDataGrid::class)->process();
        }

        return view('crypto_call_center::wallets.index');
    }
}
