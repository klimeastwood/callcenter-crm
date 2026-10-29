<?php

use Illuminate\Support\Facades\Route;
use Webkul\CryptoCallCenter\Http\Controllers\BalanceAdjustmentController;
use Webkul\CryptoCallCenter\Http\Controllers\CallLogController;
use Webkul\CryptoCallCenter\Http\Controllers\ContactUniquenessController;
use Webkul\CryptoCallCenter\Http\Controllers\CryptoWalletController;
use Webkul\CryptoCallCenter\Http\Controllers\DepositController;
use Webkul\CryptoCallCenter\Http\Controllers\WithdrawalController;

/**
 * The contact detail check of the lead form.
 *
 * The route is deliberately named under `admin.leads.*` and deliberately left
 * out of `Config/acl.php`. The core Bouncer authorizes an unmapped route against
 * the nearest mapped ancestor feature, so the check needs a lead permission -
 * the very permission that opens the form - and an account without one is still
 * refused. An own permission key would have to be granted to every role that
 * creates leads, and a role provisioned earlier would keep getting the 401 this
 * route exists to remove. See `ContactUniquenessController`.
 */
Route::controller(ContactUniquenessController::class)->prefix('leads')->group(function () {
    Route::get('check-unique-contact', 'check')->name('admin.leads.check_unique_contact');
});

Route::controller(CryptoWalletController::class)->prefix('crypto-call-center/wallets')->group(function () {
    Route::get('', 'index')->name('admin.crypto_call_center.wallets.index');
});

/**
 * The payout requests the clients staged in the trading cabinet.
 *
 * Both routes are mapped in `Config/acl.php` separately: the list is granted to
 * the call center staff, opening a request — which prints the card number — is
 * a permission of its own.
 */
Route::controller(WithdrawalController::class)->prefix('crypto-call-center/withdrawals')->group(function () {
    Route::get('', 'index')->name('admin.crypto_call_center.withdrawals.index');

    Route::get('{withdrawal}/card', 'card')->whereNumber('withdrawal')->name('admin.crypto_call_center.withdrawals.card');

    Route::post('{withdrawal}/approve', 'approve')->whereNumber('withdrawal')->name('admin.crypto_call_center.withdrawals.approve');

    Route::post('{withdrawal}/reject', 'reject')->whereNumber('withdrawal')->name('admin.crypto_call_center.withdrawals.reject');

    Route::post('{withdrawal}/complete', 'complete')->whereNumber('withdrawal')->name('admin.crypto_call_center.withdrawals.complete');
});

/**
 * The deposit requests the clients staged in the trading cabinet.
 *
 * A deposit is not credited when the client asks for it: the money reaches the
 * balance only through `approve`. The list is readable by the call center staff,
 * while opening a request - which prints the card number - and deciding it are
 * mapped to a permission of their own in `Config/acl.php`.
 */
Route::controller(DepositController::class)->prefix('crypto-call-center/deposits')->group(function () {
    Route::get('', 'index')->name('admin.crypto_call_center.deposits.index');

    Route::get('{deposit}', 'show')->whereNumber('deposit')->name('admin.crypto_call_center.deposits.show');

    Route::post('{deposit}/approve', 'approve')->whereNumber('deposit')->name('admin.crypto_call_center.deposits.approve');

    Route::post('{deposit}/reject', 'reject')->whereNumber('deposit')->name('admin.crypto_call_center.deposits.reject');
});

Route::controller(CallLogController::class)->prefix('crypto-call-center/call-logs')->group(function () {
    Route::get('', 'index')->name('admin.crypto_call_center.call_logs.index');
});

Route::controller(BalanceAdjustmentController::class)->prefix('crypto-call-center/leads')->group(function () {
    Route::post('{lead}/balance', 'store')->name('admin.crypto_call_center.balance_adjustments.store');
});
