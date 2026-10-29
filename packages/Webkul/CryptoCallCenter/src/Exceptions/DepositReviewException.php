<?php

namespace Webkul\CryptoCallCenter\Exceptions;

use RuntimeException;

/**
 * A deposit request cannot be decided: it is already reviewed, or the trading
 * side refused the credit.
 *
 * The message is already user facing: the controller shows it next to the form
 * instead of a stack trace, so every throw site translates its own text.
 */
class DepositReviewException extends RuntimeException {}
