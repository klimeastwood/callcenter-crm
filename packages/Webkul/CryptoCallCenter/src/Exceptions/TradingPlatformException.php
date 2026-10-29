<?php

namespace Webkul\CryptoCallCenter\Exceptions;

use RuntimeException;

/**
 * The trading application refused a balance change, or could not be reached.
 *
 * The message is already user facing: the controller shows it next to the form
 * instead of a stack trace, so every throw site translates its own text.
 */
class TradingPlatformException extends RuntimeException {}
