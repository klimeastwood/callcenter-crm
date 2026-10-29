<?php

use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email as SymfonyEmail;

/**
 * The SMTP settings a working `.env` resolves to.
 */
function mailTestWorkingSmtpSettings(): array
{
    return [
        'mail.default' => 'smtp',
        'mail.from.address' => 'desk@example.com',
        'mail.from.name' => 'Crypto Desk',
        'mail.mailers.smtp' => [
            'transport' => 'smtp',
            'host' => 'smtp.example.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'desk@example.com',
            'password' => 'application-password',
            'timeout' => null,
        ],
    ];
}

/**
 * Swap the mailer the command resolves for a mock, so no server is contacted.
 */
function fakeMailTestTransport(?callable $onRaw = null): void
{
    $mailer = Mockery::mock(MailerContract::class);

    Mail::shouldReceive('mailer')->once()->with('smtp')->andReturn($mailer);

    $expectation = $mailer->shouldReceive('raw')->once();

    if ($onRaw) {
        $expectation->withArgs($onRaw);
    }
}

it('sends the test message to the requested address', function () {
    config(mailTestWorkingSmtpSettings());

    $sent = null;

    fakeMailTestTransport(function (string $body, Closure $callback) use (&$sent): bool {
        $message = new Message(new SymfonyEmail);

        $callback($message);

        $sent = $message->getSymfonyMessage();

        expect($body)->toContain('test message');

        return true;
    });

    expect(Artisan::call('mail:test', ['to' => 'qa@example.com']))->toBe(0);

    expect($sent->getTo()[0]->getAddress())->toBe('qa@example.com')
        ->and($sent->getSubject())->toContain('Test message')
        ->and(Artisan::output())->toContain('accepted');
});

it('defaults to the desk mailbox the task verified', function () {
    config(mailTestWorkingSmtpSettings());

    $sent = null;

    fakeMailTestTransport(function (string $body, Closure $callback) use (&$sent): bool {
        $message = new Message(new SymfonyEmail);

        $callback($message);

        $sent = $message->getSymfonyMessage();

        return true;
    });

    expect(Artisan::call('mail:test'))->toBe(0);

    expect($sent->getTo()[0]->getAddress())->toBe('5trgb4llki7h@mail.ru');
});

it('refuses an address that is not an e-mail before contacting the server', function () {
    config(mailTestWorkingSmtpSettings());

    Mail::shouldReceive('mailer')->never();

    expect(Artisan::call('mail:test', ['to' => 'not-an-address']))->toBe(1);

    expect(Artisan::output())->toContain('is not a valid e-mail address');
});

it('names the empty SMTP variables instead of failing on the connection', function () {
    config(mailTestWorkingSmtpSettings());

    config(['mail.mailers.smtp.host' => '', 'mail.mailers.smtp.password' => '']);

    Mail::shouldReceive('mailer')->never();

    expect(Artisan::call('mail:test'))->toBe(1);

    $output = Artisan::output();

    expect($output)->toContain('MAIL_HOST is empty')
        ->and($output)->toContain('MAIL_PASSWORD is empty')
        ->and($output)->not->toContain('application-password');
});

it('never prints the mailbox password', function () {
    config(mailTestWorkingSmtpSettings());

    fakeMailTestTransport(fn (): bool => true);

    Artisan::call('mail:test');

    expect(Artisan::output())
        ->not->toContain('application-password')
        ->toContain('(20 chars)');
});

it('does not contact the server on a dry run', function () {
    config(mailTestWorkingSmtpSettings());

    Mail::shouldReceive('mailer')->never();

    expect(Artisan::call('mail:test', ['--dry-run' => true]))->toBe(0);

    expect(Artisan::output())->toContain('Dry run');
});

it('explains a rejected transport instead of crashing', function () {
    config(mailTestWorkingSmtpSettings());

    $mailer = Mockery::mock(MailerContract::class);

    Mail::shouldReceive('mailer')->once()->andReturn($mailer);

    $mailer->shouldReceive('raw')->once()->andThrow(
        new TransportException('Failed to authenticate on SMTP server with username "desk@example.com" using 4 possible authenticators.')
    );

    expect(Artisan::call('mail:test'))->toBe(1);

    $output = Artisan::output();

    expect($output)->toContain('was not sent')
        ->and($output)->toContain('rejected the credentials');
});

it('warns when the encryption of a well known port is missing', function () {
    config(mailTestWorkingSmtpSettings());

    config(['mail.mailers.smtp.encryption' => '']);

    fakeMailTestTransport(fn (): bool => true);

    Artisan::call('mail:test');

    expect(Artisan::output())->toContain('MAIL_ENCRYPTION is empty');
});
