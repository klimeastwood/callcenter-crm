<?php

namespace Webkul\CryptoCallCenter\Exceptions;

use RuntimeException;

/**
 * A payout decision the operator cannot make.
 *
 * The message is written for the operator and is printed on the request page as
 * it is: the request is already decided, the trading application has not
 * deployed the payout table, or the row disappeared between opening the page and
 * submitting it.
 */
class WithdrawalReviewException extends RuntimeException {}
