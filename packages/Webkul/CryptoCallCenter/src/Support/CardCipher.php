<?php

namespace Webkul\CryptoCallCenter\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Opens the card number the trading application sealed for a payout.
 *
 * The trading application runs on its own `APP_KEY`, so a value it encrypted
 * with Laravel's `Crypt` can never be opened here. The number is therefore
 * sealed with the secret both applications share
 * (`WITHDRAWAL_CARD_KEY` / `crypto_call_center.trading.withdrawal.card_key`,
 * AES-256-GCM with the nonce and the tag stored next to the ciphertext). GCM
 * authenticates the payload, so a tampered row fails to open instead of
 * producing a wrong card number.
 *
 * The CRM only ever decrypts: the number is written by the trading application
 * when the client enters it, and nothing in the panel stores or re-seals it.
 */
final class CardCipher
{
    /**
     * Cipher of the payload the payout desk opens.
     */
    private const CIPHER = 'aes-256-gcm';

    /**
     * Version prefix of the shared-key payload.
     */
    private const VERSION = 'v1';

    /**
     * Whether this installation has the secret the trading side sealed with.
     *
     * Checked by the payout screen so it can say "not configured" instead of
     * promising a number it cannot open.
     */
    public static function configured(): bool
    {
        return self::sharedKey() !== null;
    }

    /**
     * The card number, or null when the key does not match the row.
     */
    public static function decrypt(?string $payload): ?string
    {
        $payload = trim((string) $payload);

        if ($payload === '') {
            return null;
        }

        if (str_starts_with($payload, self::VERSION.':')) {
            return self::decryptShared($payload);
        }

        /**
         * A deployment that never configured the shared secret seals with its
         * own `APP_KEY`. Such a row opens only when both applications were
         * started from the same key, which is why the returned null is a normal
         * answer and not an error.
         */
        try {
            return Crypt::decryptString($payload);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Open a `v1` payload, or null when the secret is missing or the tag does
     * not verify.
     */
    private static function decryptShared(string $payload): ?string
    {
        $key = self::sharedKey();

        if ($key === null) {
            return null;
        }

        $parts = explode(':', $payload);

        if (count($parts) !== 4) {
            return null;
        }

        [, $nonce, $tag, $ciphertext] = $parts;

        $nonce = base64_decode($nonce, true);

        $tag = base64_decode($tag, true);

        $ciphertext = base64_decode($ciphertext, true);

        if ($nonce === false || $tag === false || $ciphertext === false || strlen($nonce) !== 12 || strlen($tag) !== 16) {
            return null;
        }

        $digits = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag);

        return $digits === false || $digits === '' ? null : $digits;
    }

    /**
     * The 32-byte key derived from the shared secret, or null when it is not
     * configured.
     */
    private static function sharedKey(): ?string
    {
        $secret = trim((string) config('crypto_call_center.trading.withdrawal.card_key', ''));

        return $secret === '' ? null : hash('sha256', $secret, true);
    }
}
