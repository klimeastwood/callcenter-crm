<?php

namespace Webkul\CryptoCallCenter\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class MailTest extends Command
{
    /**
     * The mailbox the desk uses to smoke-test the outbound transport.
     */
    protected const DEFAULT_RECIPIENT = '5trgb4llki7h@mail.ru';

    /**
     * The `.env` keys Laravel reads into `config/mail.php` for the SMTP transport.
     */
    protected const SMTP_ENV_KEYS = [
        'host' => 'MAIL_HOST',
        'port' => 'MAIL_PORT',
        'username' => 'MAIL_USERNAME',
        'password' => 'MAIL_PASSWORD',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test
                            {to='.self::DEFAULT_RECIPIENT.' : Address that receives the test message}
                            {--mailer= : Mailer from config/mail.php, defaults to the configured one}
                            {--timeout=15 : Seconds to wait for the SMTP server before failing}
                            {--dry-run : Print the resolved transport without sending anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test message through the configured mail transport';

    /**
     * Execute the console command.
     *
     * The CRM sends quotes, password resets and notifications through the same
     * transport, so a broken `.env` stays silent until a manager needs it. The
     * command therefore reports the resolved configuration first - masking the
     * password, which must not end up in a shared console log - and only then
     * contacts the server, so a configuration mistake is named instead of
     * surfacing as an SMTP stack trace.
     */
    public function handle(): int
    {
        $recipient = trim((string) $this->argument('to'));

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error("\"{$recipient}\" is not a valid e-mail address.");

            return self::FAILURE;
        }

        $mailer = trim((string) $this->option('mailer')) ?: (string) config('mail.default');

        if ($mailer === '' || ! is_array(config("mail.mailers.{$mailer}"))) {
            $this->error("The mailer \"{$mailer}\" is not declared in config/mail.php.");

            return self::FAILURE;
        }

        $transport = (string) config("mail.mailers.{$mailer}.transport", $mailer);

        $this->applyTimeout($mailer, $transport);

        $this->reportTransport($mailer, $transport, $recipient);

        $problems = $this->configurationProblems($mailer, $transport);

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }

            $this->newLine();

            $this->comment('Nothing was sent. Fix .env, then run `php artisan config:clear` and try again.');

            return self::FAILURE;
        }

        $this->reportWarnings($mailer, $transport);

        if ($this->option('dry-run')) {
            $this->newLine();

            $this->info('Dry run: the transport looks usable, nothing was sent.');

            return self::SUCCESS;
        }

        $startedAt = microtime(true);

        try {
            $this->send($mailer, $transport, $recipient);
        } catch (Throwable $exception) {
            $this->newLine();

            $this->error('The test message was not sent.');

            $this->line('  '.get_class($exception).': '.$exception->getMessage());

            $this->newLine();

            $this->comment($this->failureHint($exception));

            return self::FAILURE;
        }

        $elapsed = (int) round((microtime(true) - $startedAt) * 1000);

        $this->newLine();

        $this->info("The test message was accepted by the \"{$mailer}\" transport for {$recipient} in {$elapsed} ms.");

        $this->comment('Delivery is confirmed by the recipient: check the inbox (and the spam folder) of that mailbox.');

        return self::SUCCESS;
    }

    /**
     * Hand the message to the configured mailer.
     *
     * The mailer is named explicitly instead of using the default one, so the
     * same command can verify a candidate mailer before it is made the default.
     */
    protected function send(string $mailer, string $transport, string $recipient): void
    {
        Mail::mailer($mailer)->raw($this->body($mailer, $transport), function (Message $message) use ($recipient): void {
            $message->to($recipient)->subject($this->subject());
        });
    }

    /**
     * Body of the test message.
     *
     * The message names the installation it came from, so a desk that runs
     * several copies of the CRM can tell which `.env` was just verified.
     */
    protected function body(string $mailer, string $transport): string
    {
        $settings = (array) config("mail.mailers.{$mailer}", []);

        $host = (string) ($settings['host'] ?? '');

        $port = (string) ($settings['port'] ?? '');

        $encryption = (string) ($settings['encryption'] ?? '');

        return implode(PHP_EOL, [
            'This is a test message from the '.config('app.name').' CRM.',
            '',
            'Sent at: '.now()->format('d.m.Y H:i:s').' ('.config('app.timezone').')',
            'Application: '.config('app.name').' ['.config('app.env').']',
            'URL: '.config('app.url'),
            'Mailer: '.$mailer.' ('.$transport.')',
            'Server: '.$host.($port !== '' ? ':'.$port : '').($encryption !== '' ? ' / '.$encryption : ''),
            'From: '.$this->from(),
            '',
            'The CRM reached the mail server: outbound e-mail is configured and the credentials were accepted.',
        ]);
    }

    /**
     * Subject of the test message.
     */
    protected function subject(): string
    {
        return '[CRM] Test message from "'.config('app.name').'" ('.config('app.env').')';
    }

    /**
     * The address the message is sent from, in the RFC 5322 form.
     */
    protected function from(): string
    {
        $address = (string) config('mail.from.address');

        $name = (string) config('mail.from.name');

        return $name !== '' ? "\"{$name}\" <{$address}>" : $address;
    }

    /**
     * Print the transport the command is about to use.
     *
     * The password is masked: the point of the command is to be pasteable into
     * a ticket, and a mailbox password must not travel with it.
     */
    protected function reportTransport(string $mailer, string $transport, string $recipient): void
    {
        $settings = (array) config("mail.mailers.{$mailer}", []);

        $this->table(['Setting', 'Value'], [
            ['Mailer', $mailer],
            ['Transport', $transport],
            ['Host', (string) ($settings['host'] ?? '(not used)')],
            ['Port', (string) ($settings['port'] ?? '(not used)')],
            ['Encryption', (string) ($settings['encryption'] ?? '(none)')],
            ['Username', (string) ($settings['username'] ?? '(not used)')],
            ['Password', $this->maskedPassword($settings['password'] ?? null)],
            ['From', $this->from()],
            ['To', $recipient],
        ]);
    }

    /**
     * Why the transport cannot be used, if it cannot.
     *
     * A transport that never authenticates (`log`, `array`, `sendmail`) needs no
     * mailbox at all, so the SMTP keys are only demanded from an SMTP mailer.
     */
    protected function configurationProblems(string $mailer, string $transport): array
    {
        $settings = (array) config("mail.mailers.{$mailer}", []);

        $problems = [];

        if ($transport === 'smtp') {
            foreach (self::SMTP_ENV_KEYS as $key => $envKey) {
                if ($this->blank($settings[$key] ?? null)) {
                    $problems[] = "{$envKey} is empty, so the SMTP transport cannot connect (config(\"mail.mailers.{$mailer}.{$key}\")).";
                }
            }
        }

        if (! filter_var((string) config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
            $problems[] = 'MAIL_FROM_ADDRESS is empty or is not a valid e-mail address.';
        }

        return $problems;
    }

    /**
     * Configuration that works but is likely to bite later.
     */
    protected function reportWarnings(string $mailer, string $transport): void
    {
        if (file_exists(base_path('bootstrap/cache/config.php'))) {
            $this->warn('The configuration is cached: a `.env` edited after `php artisan config:cache` is not in effect until `php artisan config:clear`.');
        }

        if ($transport !== 'smtp') {
            return;
        }

        $settings = (array) config("mail.mailers.{$mailer}", []);

        $port = (int) ($settings['port'] ?? 0);

        $encryption = (string) ($settings['encryption'] ?? '');

        if ($encryption === '' && in_array($port, [465, 587], true)) {
            $expected = $port === 465 ? 'ssl' : 'tls';

            $this->warn("MAIL_ENCRYPTION is empty, while port {$port} normally speaks {$expected}. The server will most likely refuse the connection.");
        }

        $username = (string) ($settings['username'] ?? '');

        $from = (string) config('mail.from.address');

        if ($username !== '' && $from !== '' && strcasecmp($username, $from) !== 0) {
            $this->warn("MAIL_FROM_ADDRESS ({$from}) differs from MAIL_USERNAME ({$username}): a relay such as mail.ru usually rewrites or rejects a sender it does not own.");
        }
    }

    /**
     * Bound how long a dead server may keep the command waiting.
     *
     * `config/mail.php` leaves the timeout unset (the PHP socket default), which
     * makes a blocked port look like a frozen console; the option is applied
     * before the mailer is resolved, because the transport takes it at creation.
     */
    protected function applyTimeout(string $mailer, string $transport): void
    {
        $timeout = (int) $this->option('timeout');

        if ($transport === 'smtp' && $timeout > 0) {
            config(["mail.mailers.{$mailer}.timeout" => $timeout]);
        }
    }

    /**
     * A readable password placeholder.
     */
    protected function maskedPassword(mixed $password): string
    {
        if ($this->blank($password)) {
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
            Str::contains($message, ['535', 'authenticat', 'username and password']) => 'The server rejected the credentials. mail.ru only accepts an application password (Настройки почты → Пароль для внешних приложений); the account password will not authenticate. Check MAIL_USERNAME and MAIL_PASSWORD.',
            Str::contains($message, ['connection could not be established', 'unable to connect', 'timed out', 'stream_socket_client', 'connection refused']) => 'The mail server is unreachable: check MAIL_HOST, MAIL_PORT and MAIL_ENCRYPTION, that the provider does not block the port, and that the machine has an internet connection.',
            Str::contains($message, ['certificate', 'ssl', 'tls', 'crypto']) => 'The encrypted handshake failed: use MAIL_ENCRYPTION=ssl for port 465 and tls for port 587.',
            default => 'Check the values in .env, run `php artisan config:clear`, and see storage/logs/laravel.log for the full trace.',
        };
    }

    /**
     * Whether a configuration value carries no setting.
     */
    protected function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '') || $value === [];
    }
}
