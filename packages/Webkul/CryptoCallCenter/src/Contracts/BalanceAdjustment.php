<?php

namespace Webkul\CryptoCallCenter\Contracts;

interface BalanceAdjustment
{
    /**
     * The lead the change belongs to.
     */
    public function lead();

    /**
     * The operator that made the change; null once the account is removed.
     */
    public function admin();
}
