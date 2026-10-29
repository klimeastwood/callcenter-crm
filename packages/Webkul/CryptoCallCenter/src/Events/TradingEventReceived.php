<?php

namespace Webkul\CryptoCallCenter\Events;

use Webkul\CryptoCallCenter\Models\TradingEvent;

/**
 * A row of the shared trading log is ready to be described.
 *
 * The trading application writes straight into the shared database and knows
 * nothing about this CRM, so no application event crosses the boundary. The
 * feed reads the log and raises this event inside the CRM instead, which keeps
 * the ingestion and the side it triggers (the timeline entry) decoupled.
 */
class TradingEventReceived
{
    public function __construct(public readonly TradingEvent $tradingEvent) {}
}
