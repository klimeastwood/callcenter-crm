<?php

use Webklex\PHPIMAP\Message;
use Webkul\CryptoCallCenter\InboundEmail\MailboxEmailProcessor;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;
use Webkul\Email\InboundEmailProcessor\SendgridEmailProcessor;
use Webkul\Email\InboundEmailProcessor\WebklexImapEmailProcessor;

/**
 * Resolve the processor the `inbound-emails:process` command receives.
 */
function inboundEmailProcessor(): InboundEmailProcessor
{
    return app(InboundEmailProcessor::class);
}

/**
 * A message with the two attributes the synthetic id needs.
 */
function inboundEmailMessage(int $uid, string $folderPath): Message
{
    $message = (new ReflectionClass(Message::class))->newInstanceWithoutConstructor();

    $message->setFolderPath($folderPath);

    $message->__set('uid', $uid);

    return $message;
}

it('maps imap folder names to the folders the crm renders', function (string $name, ?string $expected): void {
    expect(MailboxEmailProcessor::supportedFolder($name))->toBe($expected);
})->with([
    ['INBOX', 'inbox'],
    ['inbox', 'inbox'],
    [' Inbox ', 'inbox'],
    ['Входящие', 'inbox'],
    ['Important', 'important'],
    ['Важные', 'important'],
    ['Starred', 'starred'],
    ['Помеченные', 'starred'],
    ['Drafts', 'draft'],
    ['Черновики', 'draft'],
    ['Sent', 'sent'],
    ['Sent Mail', 'sent'],
    ['Sent Items', 'sent'],
    ['Отправленные', 'sent'],
    ['Trash', 'trash'],
    ['Deleted Messages', 'trash'],
    ['Корзина', 'trash'],
    ['Удалённые', 'trash'],
    ['Удаленные', 'trash'],
    ['Spam', 'spam'],
    ['Junk', 'spam'],
    ['Junk E-mail', 'spam'],
    ['Спам', 'spam'],
]);

it('skips folders the crm cannot show', function (?string $name): void {
    expect(MailboxEmailProcessor::supportedFolder($name))->toBeNull();
})->with([
    'all mail' => ['All Mail'],
    'archive' => ['Archive'],
    'russian archive' => ['Архив'],
    'unknown folder' => ['My Folder'],
    'empty name' => [''],
    'null name' => [null],
]);

it('replaces the core imap processor for the imap driver', function (): void {
    config(['mail-receiver.default' => 'webklex-imap']);

    expect(inboundEmailProcessor())->toBeInstanceOf(MailboxEmailProcessor::class);
});

it('keeps the sendgrid processor for the sendgrid driver', function (): void {
    config(['mail-receiver.default' => 'sendgrid']);

    expect(inboundEmailProcessor())->toBeInstanceOf(SendgridEmailProcessor::class);
});

it('does not open the imap connection just by building the processor', function (): void {
    config([
        'mail-receiver.default' => 'webklex-imap',
        'imap.accounts.default.host' => 'imap.invalid.test',
    ]);

    $processor = inboundEmailProcessor();

    $client = new ReflectionProperty(WebklexImapEmailProcessor::class, 'client');

    /**
     * The core constructor connected here, which made every artisan command
     * that touches the container depend on a reachable mailbox.
     */
    expect($client->getValue($processor))->toBeNull();
});

it('gives a message without a message-id header a stable synthetic id', function (): void {
    config(['mail-receiver.default' => 'webklex-imap']);

    $processor = inboundEmailProcessor();

    $method = new ReflectionMethod(MailboxEmailProcessor::class, 'syntheticMessageId');

    $message = inboundEmailMessage(42, 'INBOX');

    $expected = '42.'.md5('INBOX').'@localhost';

    expect($method->invoke($processor, $message))->toBe($expected)
        ->and($method->invoke($processor, $message))->toBe($expected)
        ->and($method->invoke($processor, inboundEmailMessage(42, 'INBOX.Sub')))->not->toBe($expected);
});
