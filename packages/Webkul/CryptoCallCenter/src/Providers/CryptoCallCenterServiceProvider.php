<?php

namespace Webkul\CryptoCallCenter\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\DataGrids\Activity\ActivityDataGrid as CoreActivityDataGrid;
use Webkul\Admin\DataGrids\Contact\OrganizationDataGrid as CoreOrganizationDataGrid;
use Webkul\Admin\DataGrids\Lead\LeadDataGrid as CoreLeadDataGrid;
use Webkul\CryptoCallCenter\Bouncer;
use Webkul\CryptoCallCenter\Console\Commands\CheckInbox;
use Webkul\CryptoCallCenter\Console\Commands\LocalizeCatalog;
use Webkul\CryptoCallCenter\Console\Commands\MailTest;
use Webkul\CryptoCallCenter\Console\Commands\ProvisionOperatorRole;
use Webkul\CryptoCallCenter\Console\Commands\SeedDemoData;
use Webkul\CryptoCallCenter\Console\Commands\SyncTradingEvents;
use Webkul\CryptoCallCenter\DataGrids\Overrides\ActivityDataGrid;
use Webkul\CryptoCallCenter\DataGrids\Overrides\LeadDataGrid;
use Webkul\CryptoCallCenter\DataGrids\Overrides\OrganizationDataGrid;
use Webkul\CryptoCallCenter\InboundEmail\MailboxEmailProcessor;
use Webkul\CryptoCallCenter\Repositories\SalesOwnerUserRepository;
use Webkul\CryptoCallCenter\Repositories\TradingEventRepository;
use Webkul\CryptoCallCenter\Services\Balance\DatabaseTradingBalanceGateway;
use Webkul\CryptoCallCenter\Services\Balance\HttpTradingBalanceGateway;
use Webkul\CryptoCallCenter\Services\Balance\TradingBalanceGateway;
use Webkul\CryptoCallCenter\Services\Deposit\DatabaseDepositBalanceGateway;
use Webkul\CryptoCallCenter\Services\Deposit\DepositBalanceGateway;
use Webkul\CryptoCallCenter\Services\Deposit\HttpDepositBalanceGateway;
use Webkul\CryptoCallCenter\Services\Withdrawal\DatabaseWithdrawalReviewGateway;
use Webkul\CryptoCallCenter\Services\Withdrawal\HttpWithdrawalReviewGateway;
use Webkul\CryptoCallCenter\Services\Withdrawal\WithdrawalReviewGateway;
use Webkul\CryptoCallCenter\Support\OperatorScope;
use Webkul\CryptoCallCenter\Support\TradingBridge;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;
use Webkul\Email\InboundEmailProcessor\SendgridEmailProcessor;
use Webkul\User\Models\UserProxy;

class CryptoCallCenterServiceProvider extends ServiceProvider
{
    /**
     * Locale the package supplies, while Krayin core ships none for it.
     */
    protected const RUSSIAN_LOCALE = 'ru';

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'crypto_call_center');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'crypto_call_center');

        $this->registerRussianTranslations();

        $this->defaultLocaleToRussian();

        $this->registerBranding();

        $this->registerSidebarScroll();

        $this->preferInboundMailbox();

        $this->pinOperatorScope();

        $this->registerTradingFeed();

        $this->registerBalanceBlock();

        Route::middleware(['web', 'admin_locale', 'user'])
            ->prefix(config('app.admin_path'))
            ->group(__DIR__.'/../Routes/admin.php');

        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/menu.php', 'menu.admin');

        $this->mergeConfigFrom(__DIR__.'/../Config/acl.php', 'acl');

        $this->mergeConfigFrom(__DIR__.'/../Config/roles.php', 'crypto_call_center.roles');

        $this->mergeConfigFrom(__DIR__.'/../Config/localization.php', 'crypto_call_center.localization');

        $this->mergeConfigFrom(__DIR__.'/../Config/trading.php', 'crypto_call_center.trading');

        $this->registerRussianLocale();

        $this->registerCommands();

        $this->registerDataGrids();

        $this->registerBalanceGateway();

        $this->registerDepositGateway();

        $this->registerWithdrawalReviewGateway();

        $this->registerSalesOwnerLookup();

        $this->registerSharedContactBook();
    }

    /**
     * Share the contact book between call center accounts.
     *
     * Core registers the Bouncer under the string key `bouncer`, and both the
     * `bouncer()` helper and the `Bouncer` facade resolve that key, so the
     * binding has to replace the key itself rather than the class name. The
     * override only drops the owner filter on `admin.contacts.*` routes and is
     * documented in `Webkul\CryptoCallCenter\Bouncer`.
     */
    protected function registerSharedContactBook(): void
    {
        $this->app->singleton('bouncer', Bouncer::class);
    }

    /**
     * Register the console commands of the package.
     */
    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            ProvisionOperatorRole::class,
            LocalizeCatalog::class,
            SeedDemoData::class,
            SyncTradingEvents::class,
            MailTest::class,
            CheckInbox::class,
        ]);
    }

    /**
     * Use the package's resilient IMAP processor for the `webklex-imap` driver.
     *
     * The binding is deferred to `booted()` on purpose: `bootstrap/providers.php`
     * registers this package before `EmailServiceProvider`, whose `boot()` binds
     * the same contract to the core processor. A `booted()` callback runs after
     * every provider has booted, so this binding is the one the container keeps.
     * It resolves the processor again on each request, because the driver is
     * read from configuration at resolution time.
     */
    protected function preferInboundMailbox(): void
    {
        $this->app->booted(function (): void {
            $this->app->bind(InboundEmailProcessor::class, function ($app): InboundEmailProcessor {
                $driver = config('mail-receiver.default');

                if ($driver === 'sendgrid') {
                    return $app->make(SendgridEmailProcessor::class);
                }

                if ($driver === 'webklex-imap') {
                    return $app->make(MailboxEmailProcessor::class);
                }

                throw new \Exception("Unsupported mail receiver driver [{$driver}].");
            });
        });
    }

    /**
     * Show the trading events on the lead card and keep the timeline in sync.
     *
     * The trading application shares the database but not the process: it
     * cannot raise an event inside the CRM, so the new rows of its log are read
     * by the scheduler and written into the lead timeline by a listener. The
     * card block above the timeline reads the same log directly, so a manager
     * sees the latest trades even before the next run of the feed.
     *
     * Both entry points stay silent on an installation that has not deployed
     * the trading side and therefore has no `trading_events` table.
     */
    protected function registerTradingFeed(): void
    {
        Event::listen('admin.leads.view.activities.before', function ($event): void {
            if (! TradingEventRepository::available()) {
                return;
            }

            $event->addTemplate('crypto_call_center::leads.trading-events');
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command('crypto-call-center:sync-trading-events')
                ->everyMinute()
                ->withoutOverlapping();
        });
    }

    /**
     * Show the manual balance block on the lead card.
     *
     * The template decides what to render - permission, ownership, the link to
     * the trader account - but the hook itself is registered only when a change
     * can actually be delivered, so an installation without the bridge does not
     * get an empty card header. The listener stays silent when the page has no
     * lead attached, which keeps it safe for every other render of the event.
     */
    protected function registerBalanceBlock(): void
    {
        Event::listen('admin.leads.view.activities.before', function ($event): void {
            if (! TradingBridge::ready()) {
                return;
            }

            $event->addTemplate('crypto_call_center::leads.balance-adjustments');
        });
    }

    /**
     * Bind the balance gateway to the transport declared in the config.
     *
     * The service only depends on the interface, so the driver can be changed
     * per installation without touching a class: `database` writes into the
     * shared tables directly, anything else goes through the bridge, which
     * falls back to a direct write only when that is allowed explicitly.
     */
    protected function registerBalanceGateway(): void
    {
        $this->app->bind(TradingBalanceGateway::class, function ($app): TradingBalanceGateway {
            return TradingBridge::driver() === 'database'
                ? $app->make(DatabaseTradingBalanceGateway::class)
                : $app->make(HttpTradingBalanceGateway::class);
        });
    }

    /**
     * Bind the deposit gateway to the transport declared in the config.
     *
     * Approved deposits follow the same rule as a manual balance change: the
     * trading application owns the balance, so the CRM asks over the bridge and
     * only writes into the shared tables when the configuration allows the
     * fallback. The implementation is idempotent per deposit request reference.
     */
    protected function registerDepositGateway(): void
    {
        $this->app->bind(DepositBalanceGateway::class, function ($app): DepositBalanceGateway {
            return TradingBridge::driver() === 'database'
                ? $app->make(DatabaseDepositBalanceGateway::class)
                : $app->make(HttpDepositBalanceGateway::class);
        });
    }

    /**
     * Bind the payout-review gateway to the transport declared in the config.
     *
     * Approving or rejecting a payout writes the request row the client reads,
     * and that row belongs to the trading application. The bridge is therefore
     * the primary path and the shared table is only the fallback the
     * configuration allows - the same rule as the deposit credit, without a
     * balance to move. The implementation is idempotent per request, so a retry
     * of a lost response cannot decide the payout twice.
     */
    protected function registerWithdrawalReviewGateway(): void
    {
        $this->app->bind(WithdrawalReviewGateway::class, function ($app): WithdrawalReviewGateway {
            return TradingBridge::driver() === 'database'
                ? $app->make(DatabaseWithdrawalReviewGateway::class)
                : $app->make(HttpWithdrawalReviewGateway::class);
        });
    }

    /**
     * Offer only the staff accounts as a Sales Owner.
     *
     * The "Sales Owner" field of a lead is a system attribute with the `users`
     * lookup, so its dropdown reads `attribute_lookups.users.repository`.
     * Pointing that entry at the package repository - whose model carries a
     * scope dropping the trading clients - narrows the dropdown without editing
     * the attribute, the Admin view or the repository of the core. The full
     * entry is written, because `mergeConfigFrom()` replaces a nested array as a
     * whole rather than merging it key by key.
     */
    protected function registerSalesOwnerLookup(): void
    {
        $lookup = config('attribute_lookups.users', []);

        config(['attribute_lookups.users' => array_merge((array) $lookup, [
            'name' => $lookup['name'] ?? 'Sales Owners',
            'repository' => SalesOwnerUserRepository::class,
        ])]);
    }

    /**
     * Serve the package datagrid subclasses.
     *
     * Krayin prints every mass action a grid declares, whatever the role holds:
     * the "Delete" action of the lead, activity and organization screens is only
     * refused by the middleware once the request is sent. The controllers
     * resolve their grid through the container (`datagrid(LeadDataGrid::class)`
     * is `app(LeadDataGrid::class)`), so binding the name here swaps the
     * implementation without editing a single core file, and every subclass only
     * filters the mass actions it inherits.
     */
    protected function registerDataGrids(): void
    {
        $this->app->bind(CoreLeadDataGrid::class, LeadDataGrid::class);

        $this->app->bind(CoreActivityDataGrid::class, ActivityDataGrid::class);

        $this->app->bind(CoreOrganizationDataGrid::class, OrganizationDataGrid::class);
    }

    /**
     * Pin every account holding a packaged role to the declared data scope.
     *
     * Krayin keeps `view_permission` on the user, so a role-level expectation
     * has to be enforced where the user is written. Listening on `saving`
     * covers the Settings → Users form, the API, seeders and tinker alike:
     * whatever scope the form submits is rewritten before the row is stored, so
     * an operator can never be widened to `group` or `global` and read the leads
     * of other operators, while a moderator is always pinned to `global` and
     * cannot be narrowed away from the desk it supervises.
     *
     * A package config without `view_permission` leaves the administrator's
     * choice untouched, and the listener does nothing while the role has not
     * been provisioned yet.
     */
    protected function pinOperatorScope(): void
    {
        $userModel = UserProxy::modelClass();

        $userModel::saving(function ($user): void {
            OperatorScope::enforce($user);
        });
    }

    /**
     * Add Russian to the locales offered by Configuration → General → Locale.
     *
     * `Core::locales()` builds the dropdown out of `app.available_locales`, so a
     * key added here shows up without editing `config/app.php`. The list is
     * alphabetical, so the new key is sorted into place.
     */
    protected function registerRussianLocale(): void
    {
        $locales = config('app.available_locales', []);

        if (isset($locales[self::RUSSIAN_LOCALE])) {
            return;
        }

        $locales[self::RUSSIAN_LOCALE] = 'Русский';

        ksort($locales);

        config(['app.available_locales' => $locales]);
    }

    /**
     * Merge the package's Russian lines over the core translation groups.
     *
     * Krayin ships no `ru` directory at all, so a Russian session would render
     * the raw `admin::app.*` keys. Every file under
     * `Resources/lang/overrides/{locale}/{namespace}/{group}.php` is added to
     * the translator for that locale.
     *
     * The group is loaded first on purpose: `Translator::addLines()` writes
     * straight into the loaded lines, and writing into a group that is still
     * unloaded would make the translator treat this partial array as the whole
     * group - every key the file does not mention would then render as a raw key
     * instead of falling back to English.
     */
    protected function registerRussianTranslations(): void
    {
        $translator = $this->app['translator'];

        $locale = self::RUSSIAN_LOCALE;

        $files = glob(__DIR__.'/../Resources/lang/overrides/'.$locale.'/*/*.php') ?: [];

        foreach ($files as $file) {
            $namespace = basename(dirname($file));

            $group = basename($file, '.php');

            $translator->load($namespace, $group, $locale);

            /**
             * `Translator::addLines()` splits every key on its first dot and
             * treats that segment as the group, so the file's lines are nested
             * under their group name before being flattened.
             */
            $translator->addLines(Arr::dot([$group => require $file]), $locale, $namespace);
        }
    }

    /**
     * Run the panel in Russian until somebody chooses otherwise.
     *
     * The same mechanism as the footer default: `core()->getConfigData()` reads
     * the `core_config` table first and only falls back to the field default
     * when nothing is saved. An install that never touched the locale therefore
     * opens in Russian, a saved `en` still wins, and the choice stays editable
     * from Configuration → General → Locale.
     */
    protected function defaultLocaleToRussian(): void
    {
        $coreConfig = config('core_config', []);

        foreach ($coreConfig as $itemKey => $item) {
            if (($item['key'] ?? null) !== 'general.general.locale_settings') {
                continue;
            }

            foreach ($item['fields'] ?? [] as $fieldKey => $field) {
                if (($field['name'] ?? null) === 'locale') {
                    $coreConfig[$itemKey]['fields'][$fieldKey]['default'] = self::RUSSIAN_LOCALE;
                }
            }
        }

        config(['core_config' => $coreConfig]);
    }

    /**
     * Rebrand the admin panel without editing a single Krayin file.
     *
     * Krayin prints its branding on three independent surfaces, each of which
     * needs its own extension point:
     *
     *  - the header logo and the logo of the mobile drawer are anonymous Blade
     *    components, so a view hint prepended to the `admin` namespace replaces
     *    them (`Resources/views/overrides/...`);
     *  - the footer of the authenticated layout prints the value of the
     *    `general.settings.footer.label` system config field, so the fallback of
     *    that field is supplied instead of the template;
     *  - the footer of the login / forgot-password / reset-password screens is
     *    the `admin::app.components.layouts.powered-by.description` line.
     */
    protected function registerBranding(): void
    {
        /**
         * Registering the hint once every provider has booted means the
         * `admin` hint of the Admin package is already in place. Prepending
         * puts our files first while every other `admin::` view still falls
         * through to the original.
         */
        $this->app->booted(function (): void {
            View::prependNamespace('admin', __DIR__.'/../Resources/views/overrides');
        });

        $this->localizeFooterLabel();

        /**
         * The branding is deliberately language-independent, so a locale switch
         * re-applies the same English line instead of translating it.
         */
        Event::listen(LocaleUpdated::class, function (): void {
            $this->localizeFooterLabel();
        });

        View::composer('admin::sessions.*', function (): void {
            $this->overridePoweredByLine();
        });
    }

    /**
     * Let the desktop sidebar scroll when the menu outgrows the viewport.
     *
     * Krayin's menu wrapper is `overflow-hidden`, so on a laptop screen the last
     * sidebar entries of a long menu are cut off instead of becoming reachable.
     * Copying the sidebar Blade for one utility class would create another file
     * to re-sync on every Krayin upgrade, so the fix is a single CSS rule that
     * is pushed into the `styles` stack of the layout. The rule itself and the
     * reasoning behind its scope live in `partials/sidebar-scroll`.
     *
     * The push happens from a view composer because the layout prints
     * `@stack('styles')` in the `<head>`, before the sidebar and any page view
     * are rendered; a `@push` from the sidebar override would come too late.
     */
    protected function registerSidebarScroll(): void
    {
        View::composer('admin::components.layouts.index', function ($view): void {
            $view->getFactory()->startPush(
                'styles',
                view('crypto_call_center::partials.sidebar-scroll')->render()
            );
        });
    }

    /**
     * The branding line, always in English.
     *
     * Branding is not UI copy: it names the vendor on every screen, including
     * the Russian ones, so the `en` file is the single source of truth and a
     * translated `ru` line can never leak into the footer.
     */
    protected function brandingLine(): string
    {
        return trans('crypto_call_center::app.branding.powered-by', [], 'en');
    }

    /**
     * Rebrand the footer of the authenticated layout.
     *
     * `core()->getConfigData('general.settings.footer.label')` reads the
     * `core_config` table first and only falls back to the field default when
     * nothing is saved, so a shipped default rebrands an install that never
     * touched the setting - and the wording stays editable from
     * Configuration → General → Settings.
     *
     * The fallback is resolved by `SystemConfig::getDefaultConfig()` as
     * `Config::get('settings.footer.label', $fieldDefault)`, i.e. from the live
     * config repository, while the `core_config` array itself is snapshotted on
     * the first `getConfigData()` call (the locale middleware, before the
     * request locale is known). Writing the line into `settings.footer.label`
     * therefore supplies the default on every request, and a saved footer text
     * still wins because the table is read before the default.
     */
    protected function localizeFooterLabel(): void
    {
        config(['settings.footer.label' => $this->brandingLine()]);
    }

    /**
     * Replace the "powered by" line of the unauthenticated screens.
     *
     * The session views pass the Krayin and Webkul links as `:krayin` / `:webkul`
     * placeholders into that translation line, so overriding the line
     * leaves the templates untouched.
     *
     * The `admin::app` group is loaded explicitly first: `Translator::addLines()`
     * writes straight into the loaded lines, and writing one key into a group
     * that is still unloaded would make the translator treat that partial array
     * as the whole group - every other `admin::app.*` string on those screens
     * would then render as a raw key.
     */
    protected function overridePoweredByLine(): void
    {
        $locale = $this->app->getLocale();

        $translator = $this->app['translator'];

        $translator->load('admin', 'app', $locale);

        $translator->addLines([
            'app.components.layouts.powered-by.description' => $this->brandingLine(),
        ], $locale, 'admin');
    }
}
