<?php

namespace Webkul\CryptoCallCenter\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Webkul\CryptoCallCenter\Events\TradingEventReceived;
use Webkul\CryptoCallCenter\Listeners\AssignLeadSalesOwner;
use Webkul\CryptoCallCenter\Listeners\TradingActivityListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * Crypto call center listeners are registered here so that nothing has to
     * be added to the core providers of the Admin package.
     *
     * @var array
     */
    protected $listen = [
        TradingEventReceived::class => [
            TradingActivityListener::class,
        ],

        /**
         * `lead.create.after` is dispatched by the lead controller of the Admin
         * package with the created lead as the payload, and by no model event:
         * a lead written by a seeder or an importer keeps the owner it declares.
         */
        'lead.create.after' => [
            AssignLeadSalesOwner::class,
        ],
    ];
}
