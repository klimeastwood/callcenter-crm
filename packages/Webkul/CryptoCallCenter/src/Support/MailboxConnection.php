<?php

namespace Webkul\CryptoCallCenter\Support;

use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Client as ImapClient;

/**
 * Resolves and opens the inbound IMAP mailbox.
 *
 * Two problems of the core processor are solved here.
 *
 * First, the connection is opened only when it is asked for. Krayin's
 * `WebklexImapEmailProcessor` connects in its constructor, and the container
 * builds the processor just to list the `inbound-emails:process` command, so
 * every unrelated artisan call (`php artisan mail:test`, `migrate`, `about`)
 * tried to reach the mail server and died when the credentials were wrong.
 *
 * Second, the settings are read the same way the core processor reads them -
 * the values saved in Configuration → Email → IMAP first, the `imap.accounts.default`
 * block (the `IMAP_*` variables of `.env`) as the fallback - but `validate_cert`
 * is parsed as a boolean instead of being cast, so a saved `0` or a `.env`
 * value of `false` actually disables the certificate check.
 */
class MailboxConnection
{
    /**
     * Settings the admin may override in Configuration → Email → IMAP.
     */
    protected const OVERRIDABLE = ['host', 'port', 'encryption', 'username', 'password'];

    /**
     * The resolved account configuration, ready for the IMAP client.
     */
    public static function config(): array
    {
        $config = (array) config('imap.accounts.default');

        foreach (self::OVERRIDABLE as $key) {
            $value = core()->getConfigData('email.imap.account.'.$key);

            if ($value !== null && $value !== '') {
                $config[$key] = $value;
            }
        }

        $config['validate_cert'] = self::toBoolean(
            core()->getConfigData('email.imap.account.validate_cert'),
            (bool) ($config['validate_cert'] ?? true)
        );

        return $config;
    }

    /**
     * Open a connection to the mailbox, or fail with the server's own error.
     */
    public static function client(): ImapClient
    {
        $client = Client::make(self::config());

        $client->connect();

        if (! $client->isConnected()) {
            throw new \RuntimeException('Unable to connect to the IMAP server.');
        }

        return $client;
    }

    /**
     * Read a stored or declared flag without turning "false" into `true`.
     */
    protected static function toBoolean(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
