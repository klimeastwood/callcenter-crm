<?php

namespace Webkul\CryptoCallCenter\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\CryptoCallCenter\Models\BalanceAdjustment;
use Webkul\CryptoCallCenter\Models\CallLog;
use Webkul\CryptoCallCenter\Models\CryptoWallet;
use Webkul\CryptoCallCenter\Models\DepositRequest;
use Webkul\CryptoCallCenter\Models\TradingEvent;
use Webkul\CryptoCallCenter\Models\WithdrawalRequest;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        BalanceAdjustment::class,
        CallLog::class,
        CryptoWallet::class,
        DepositRequest::class,
        TradingEvent::class,
        WithdrawalRequest::class,
    ];
}
