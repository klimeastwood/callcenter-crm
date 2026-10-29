<?php

namespace Webkul\CryptoCallCenter\InboundEmail;

use Illuminate\Support\Facades\Log;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Message;
use Webkul\CryptoCallCenter\Support\MailboxConnection;
use Webkul\Email\Enums\SupportedFolderEnum;
use Webkul\Email\Helpers\HtmlFilter;
use Webkul\Email\InboundEmailProcessor\WebklexImapEmailProcessor;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;

/**
 * IMAP processor that survives a real mailbox.
 *
 * It replaces the core `WebklexImapEmailProcessor` for the `webklex-imap`
 * driver and fixes three defects that keep inbound mail out of the CRM.
 *
 * 1. The core processor opens the IMAP connection in its constructor. The
 *    container builds it just to register the `inbound-emails:process`
 *    command, so every artisan call fails when the mailbox is unreachable -
 *    including commands that have nothing to do with mail. Here the
 *    connection is opened on first use and closed only if it was opened.
 *
 * 2. The core processor walks the folder tree down to its leaves and skips
 *    every folder that has children (`processMessagesFromLeafFolders`). A
 *    mailbox whose INBOX reports child folders therefore never imports INBOX
 *    itself. Every folder is visited here.
 *
 * 3. The folder mapping knows six English names only, and anything else is
 *    stored under an empty folder name - invisible in the CRM. Mail.ru, Yandex
 *    and Outlook report localized names (`Отправленные`, `Спам`, `Корзина`,
 *    `Sent`, `Sent Items`, `Junk`), so messages were imported into a folder
 *    no screen shows. The mapping below covers those names and unsupported
 *    folders are skipped instead of being written invisibly.
 */
class MailboxEmailProcessor extends WebklexImapEmailProcessor
{
    /**
     * How far back the first import reaches, in days. The same window the
     * core processor uses; the scheduler keeps the mailbox up to date after
     * that, and already imported messages are skipped by `message_id`.
     */
    protected const LOOKBACK_DAYS = 10;

    /**
     * IMAP folder names mapped to the folders the CRM actually renders.
     *
     * Keys are lowercase; Cyrillic is lowercased with `mb_strtolower`.
     */
    protected const FOLDER_ALIASES = [
        'inbox' => SupportedFolderEnum::INBOX->value,
        'входящие' => SupportedFolderEnum::INBOX->value,
        'important' => SupportedFolderEnum::IMPORTANT->value,
        'важные' => SupportedFolderEnum::IMPORTANT->value,
        'starred' => SupportedFolderEnum::STARRED->value,
        'помеченные' => SupportedFolderEnum::STARRED->value,
        'drafts' => SupportedFolderEnum::DRAFT->value,
        'draft' => SupportedFolderEnum::DRAFT->value,
        'черновики' => SupportedFolderEnum::DRAFT->value,
        'sent' => SupportedFolderEnum::SENT->value,
        'sent mail' => SupportedFolderEnum::SENT->value,
        'sent items' => SupportedFolderEnum::SENT->value,
        'sent messages' => SupportedFolderEnum::SENT->value,
        'отправленные' => SupportedFolderEnum::SENT->value,
        'trash' => SupportedFolderEnum::TRASH->value,
        'deleted items' => SupportedFolderEnum::TRASH->value,
        'deleted messages' => SupportedFolderEnum::TRASH->value,
        'удалённые' => SupportedFolderEnum::TRASH->value,
        'удаленные' => SupportedFolderEnum::TRASH->value,
        'корзина' => SupportedFolderEnum::TRASH->value,
        'spam' => SupportedFolderEnum::SPAM->value,
        'junk' => SupportedFolderEnum::SPAM->value,
        'junk e-mail' => SupportedFolderEnum::SPAM->value,
        'junk email' => SupportedFolderEnum::SPAM->value,
        'спам' => SupportedFolderEnum::SPAM->value,
    ];

    /**
     * Folders that hold copies of messages already stored under their own
     * folder, so importing them would only duplicate rows.
     */
    protected const IGNORED_FOLDERS = [
        'all mail',
        'archive',
        'архив',
    ];

    /**
     * The repositories are assigned without calling the parent constructor:
     * it opens the IMAP connection, and the container resolves this class on
     * every artisan boot just to list the inbound command.
     */
    public function __construct(
        EmailRepository $emailRepository,
        AttachmentRepository $attachmentRepository,
        HtmlFilter $htmlFilter
    ) {
        $this->emailRepository = $emailRepository;

        $this->attachmentRepository = $attachmentRepository;

        $this->htmlFilter = $htmlFilter;
    }

    /**
     * Close the connection only when it was actually opened.
     */
    public function __destruct()
    {
        if ($this->client instanceof Client && $this->client->isConnected()) {
            $this->client->disconnect();
        }
    }

    /**
     * Import the recent messages of every folder of the mailbox.
     *
     * A mailbox that cannot be reached is logged once and reported as a plain
     * failure, so the scheduler and the console show the server's own reason
     * instead of a stack trace through the IMAP library.
     */
    public function processMessagesFromAllFolders()
    {
        try {
            $this->processFolderTree($this->client()->getFolders());
        } catch (Throwable $exception) {
            Log::error('Inbound mail: the mailbox could not be read.', [
                'error' => $exception->getMessage(),
            ]);

            throw new \RuntimeException('Inbound mail: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Store one message, mapping its folder to a folder the CRM renders.
     *
     * The body of the method follows the core processor; the folder is
     * resolved through the alias map above, messages of folders without a
     * CRM counterpart are skipped, and a message without a `Message-ID`
     * header gets a stable synthetic id so the next run does not import it
     * again.
     *
     * @param  ?Message  $message
     */
    public function processMessage($message = null): void
    {
        $folderName = static::supportedFolder($message->getFolder()?->name);

        if ($folderName === null) {
            return;
        }

        $attributes = $message->getAttributes();

        $messageId = isset($attributes['message_id'])
            ? (string) $attributes['message_id']->first()
            : '';

        if ($messageId === '') {
            $messageId = $this->syntheticMessageId($message);
        }

        $email = $this->emailRepository->findOneByField('message_id', $messageId);

        if ($email) {
            return;
        }

        $replyToEmails = $this->getEmailsByAttributeCode($attributes, 'to');

        foreach ($replyToEmails as $to) {
            if ($email = $this->emailRepository->findOneWhere(['message_id' => $to])) {
                break;
            }
        }

        if (! isset($email) && isset($attributes['in_reply_to'])) {
            $inReplyTo = $attributes['in_reply_to']->first();

            $email = $this->emailRepository->findOneWhere(['message_id' => $inReplyTo]);

            if (! $email) {
                $email = $this->emailRepository->findOneWhere([['reference_ids', 'like', '%'.$inReplyTo.'%']]);
            }
        }

        $references = [$messageId];

        if (! isset($email) && isset($attributes['references'])) {
            array_push($references, ...$attributes['references']->all());

            foreach ($references as $reference) {
                if ($email = $this->emailRepository->findOneWhere([['reference_ids', 'like', '%'.$reference.'%']])) {
                    break;
                }
            }
        }

        $parentEmail = null;

        if ($email) {
            $email = $this->emailRepository->findThreadRoot($email);

            $parentEmail = $this->emailRepository->update([
                'folders' => array_values(array_unique(array_merge($email->folders, [$folderName]))),
                'reference_ids' => array_values(array_unique(array_merge($email->reference_ids ?? [], $references))),
            ], $email->id);
        }

        $from = isset($attributes['from']) ? $attributes['from']->first() : null;

        $createdAt = $message->date;

        $email = $this->emailRepository->create([
            'from' => $from?->mail,
            'subject' => isset($attributes['subject']) ? $attributes['subject']->first() : null,
            'name' => $from?->personal,
            /**
             * Filtered on the way in, as the other inbound processor's bodies are: the message
             * comes from outside the installation, and the mailbox renders what is stored.
             */
            'reply' => $this->htmlFilter->process(
                $message->bodies['html'] ?? $message->bodies['text'] ?? '',
                ''
            ),
            'is_read' => (int) $message->flags()->has('seen'),
            'folders' => [$folderName],
            'reply_to' => $this->getEmailsByAttributeCode($attributes, 'to'),
            'cc' => $this->getEmailsByAttributeCode($attributes, 'cc'),
            'bcc' => $this->getEmailsByAttributeCode($attributes, 'bcc'),
            'source' => 'email',
            'user_type' => 'person',
            'unique_id' => $messageId,
            'message_id' => $messageId,
            'reference_ids' => $references,
            'created_at' => $createdAt ? $this->convertToDesiredTimezone($createdAt) : now(),
            'parent_id' => $parentEmail?->id,
        ]);

        if ($message->hasAttachments()) {
            $this->attachmentRepository->uploadAttachments($email, [
                'source' => 'email',
                'attachments' => $message->getAttachments(),
            ]);
        }
    }

    /**
     * Resolve the CRM folder for an IMAP folder name.
     *
     * Returns `null` for folders the CRM cannot show, so callers can skip them
     * instead of writing rows under an empty folder name.
     */
    public static function supportedFolder(?string $name): ?string
    {
        $name = mb_strtolower(trim((string) $name));

        if ($name === '' || in_array($name, self::IGNORED_FOLDERS, true)) {
            return null;
        }

        return self::FOLDER_ALIASES[$name] ?? null;
    }

    /**
     * The lazily opened connection, shared by every folder of one run.
     */
    protected function client(): Client
    {
        if (! $this->client instanceof Client) {
            $this->client = MailboxConnection::client();
        }

        return $this->client;
    }

    /**
     * Walk the whole folder tree, parents included.
     */
    protected function processFolderTree(iterable $folders): void
    {
        foreach ($folders as $folder) {
            $this->processFolder($folder);

            if ($folder->children->isNotEmpty()) {
                $this->processFolderTree($folder->children);
            }
        }
    }

    /**
     * Import the recent messages of one folder.
     *
     * A folder the CRM cannot show is skipped, and a folder whose messages
     * cannot be read is logged and skipped: one broken subscription folder
     * must not stop INBOX from being imported.
     */
    protected function processFolder($folder): void
    {
        if (static::supportedFolder($folder->name) === null) {
            return;
        }

        try {
            $folder->query()
                ->since(now()->subDays(self::LOOKBACK_DAYS))
                ->get()
                ->each(function ($message): void {
                    $this->processMessage($message);
                });
        } catch (Throwable $exception) {
            Log::warning('Inbound mail: folder skipped.', [
                'folder' => $folder->name,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * A stable id for a message without a `Message-ID` header.
     *
     * UID and folder identify a message within a mailbox, so the fallback
     * keeps the duplicate check working for every following run.
     */
    protected function syntheticMessageId(Message $message): string
    {
        return sprintf('%s.%s@localhost', $message->getUid(), md5($message->getFolderPath()));
    }
}
