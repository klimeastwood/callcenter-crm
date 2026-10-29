<?php

namespace Webkul\CryptoCallCenter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;
use Webkul\CryptoCallCenter\InboundEmail\MailboxEmailProcessor;
use Webkul\CryptoCallCenter\Support\MailboxConnection;

/**
 * Diagnose the inbound mailbox.
 *
 * `inbound-emails:process` only reports "no messages" both when the mailbox is
 * empty and when nothing could be read, and a wrong folder mapping makes an
 * imported message invisible in the CRM. This command prints what the server
 * actually answers: the resolved settings, the folder list with its CRM
 * counterpart, and the newest messages per folder.
 */
class CheckInbox extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:inbox
                            {--days=10 : Count messages received during the last N days}
                            {--limit=5 : Print this many newest messages per folder}
                            {--folders : List the folders only, without touching the messages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connect to the inbound IMAP mailbox, list its folders and show recent messages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $limit = max(0, (int) $this->option('limit'));

        $config = MailboxConnection::config();

        $this->reportSettings($config);

        try {
            $client = MailboxConnection::client();
        } catch (Throwable $exception) {
            $this->error('Connection failed: '.$exception->getMessage());

            $this->newLine();

            $this->comment($this->failureHint($exception));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Connected to %s:%s as %s.',
            $config['host'] ?? '?',
            $config['port'] ?? '?',
            $config['username'] ?? '?'
        ));

        try {
            $folders = $client->getFolders(false);

            $this->reportFolders($folders, $days);

            if ($this->option('folders')) {
                return self::SUCCESS;
            }

            $this->reportMessages($folders, $days, $limit);
        } catch (Throwable $exception) {
            $this->error('The server refused the folder listing: '.$exception->getMessage());

            $this->newLine();

            $this->comment($this->failureHint($exception));

            return self::FAILURE;
        } finally {
            if ($client->isConnected()) {
                $client->disconnect();
            }
        }

        $this->newLine();

        $this->info('Done. Run `php artisan inbound-emails:process` to import these messages.');

        return self::SUCCESS;
    }

    /**
     * Print the settings the client will use, with the password masked.
     */
    protected function reportSettings(array $config): void
    {
        $this->table(['Setting', 'Value'], [
            ['Driver', (string) config('mail-receiver.default')],
            ['Host', (string) ($config['host'] ?? '(empty)')],
            ['Port', (string) ($config['port'] ?? '(empty)')],
            ['Encryption', (string) ($config['encryption'] ?? '(none)')],
            ['Username', (string) ($config['username'] ?? '(empty)')],
            ['Password', $this->maskedPassword($config['password'] ?? null)],
            ['Validate certificate', ($config['validate_cert'] ?? true) ? 'yes' : 'no'],
        ]);

        if (! $this->option('folders') && file_exists(base_path('bootstrap/cache/config.php'))) {
            $this->warn('The configuration is cached: a `.env` edited after `php artisan config:cache` is not in effect until `php artisan config:clear`.');
        }
    }

    /**
     * Print every folder with the folder the CRM would import it into.
     */
    protected function reportFolders($folders, int $days): void
    {
        $rows = [];

        $mapped = 0;

        foreach ($folders as $folder) {
            $crmFolder = MailboxEmailProcessor::supportedFolder($folder->name);

            if ($crmFolder !== null) {
                $mapped++;
            }

            $rows[] = [
                $folder->name,
                $crmFolder ?? '(not imported)',
                $this->messageCount($folder),
                $crmFolder === null ? '-' : $this->recentCount($folder, $days),
            ];
        }

        $this->newLine();

        $this->table(['IMAP folder', 'CRM folder', 'Messages', "Last {$days} days"], $rows);

        if ($mapped === 0) {
            $this->warn('None of the folders maps to a CRM folder, so the import would skip every message.');
        }
    }

    /**
     * Print the newest messages of every folder the import would read.
     */
    protected function reportMessages($folders, int $days, int $limit): void
    {
        if ($limit === 0) {
            return;
        }

        $printed = 0;

        foreach ($folders as $folder) {
            $crmFolder = MailboxEmailProcessor::supportedFolder($folder->name);

            if ($crmFolder === null) {
                continue;
            }

            try {
                $messages = $folder->query()
                    ->since(now()->subDays($days))
                    ->setFetchBody(false)
                    ->get();
            } catch (Throwable $exception) {
                $this->warn("Could not read {$folder->name}: {$exception->getMessage()}");

                continue;
            }

            $recent = $messages
                ->sortByDesc(fn ($message) => $message->date?->getTimestamp() ?? 0)
                ->take($limit);

            if ($recent->isEmpty()) {
                continue;
            }

            $this->newLine();

            $this->info(sprintf('%s → %s (%d message(s) shown)', $folder->name, $crmFolder, $recent->count()));

            $rows = [];

            foreach ($recent as $message) {
                $from = $message->from->first();

                $rows[] = [
                    (string) $message->uid,
                    $message->date?->format('Y-m-d H:i') ?? '?',
                    $from ? trim(($from->personal ? $from->personal.' ' : '').'<'.$from->mail.'>') : '?',
                    Str::limit((string) $message->subject, 70),
                ];
            }

            $this->table(['UID', 'Date', 'From', 'Subject'], $rows);

            $printed += $recent->count();
        }

        if ($printed === 0) {
            $this->newLine();

            $this->comment('No messages in the supported folders during the selected window. Send a message to the mailbox and run the command again.');
        }
    }

    /**
     * The total number of messages a folder reports.
     */
    protected function messageCount($folder): string
    {
        try {
            return (string) ($folder->getStatus()['messages'] ?? '?');
        } catch (Throwable) {
            return '?';
        }
    }

    /**
     * How many messages the folder holds inside the import window.
     */
    protected function recentCount($folder, int $days): string
    {
        try {
            return (string) $folder->query()
                ->since(now()->subDays($days))
                ->setFetchBody(false)
                ->get()
                ->count();
        } catch (Throwable) {
            return '?';
        }
    }

    /**
     * A readable password placeholder.
     */
    protected function maskedPassword(mixed $password): string
    {
        if ($password === null || (is_string($password) && trim($password) === '')) {
            return '(empty)';
        }

        return str_repeat('*', 8).' ('.strlen((string) $password).' chars)';
    }

    /**
     * What usually causes this failure.
     */
    protected function failureHint(Throwable $exception): string
    {
        $message = Str::lower($exception->getMessage());

        return match (true) {
            Str::contains($message, ['authenticat', 'application password', 'login fail', 'authfailed', 'invalid credentials']) => 'Mail.ru rejected the credentials. Two settings are required: enable IMAP access in the mailbox (Настройки → Все настройки → Почта → Доступ по протоколу IMAP) and create an application password (Настройки → Безопасность → Пароли для внешних приложений). Put that password into IMAP_PASSWORD in .env and run `php artisan config:clear`; the account password will not authenticate.',
            Str::contains($message, ['connection refused', 'timed out', 'unable to connect', 'stream_socket_client', 'getaddrinfo', 'could not connect']) => 'The IMAP server is unreachable: check IMAP_HOST, IMAP_PORT and IMAP_ENCRYPTION, that the provider does not block the port, and that the machine has an internet connection. Use imap.mail.ru:993 with ssl for Mail.ru.',
            Str::contains($message, ['certificate', 'ssl', 'tls', 'crypto']) => 'The encrypted handshake failed: port 993 expects ssl (IMAP_ENCRYPTION=ssl). If the host uses a self-signed certificate, set IMAP_VALIDATE_CERT=false.',
            default => 'Check the values in .env, run `php artisan config:clear`, and see storage/logs/laravel.log for the full trace.',
        };
    }
}
