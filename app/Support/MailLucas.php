<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Mail aan Lucas, via de relay van srv6, vanuit juno@lucasjohnston.nl. De ontvanger staat hier vast en is
 * nergens in te stellen.
 */
final class MailLucas
{
    public const RECIPIENT = 'lrjohnston@protonmail.com';

    public static function send(string $subject, string $html): void
    {
        Mail::html($html, fn ($message) => $message->to(self::RECIPIENT)->subject($subject));
    }

    /**
     * Als send(), maar een mislukte mail gooit niet: voor de scheduler, die door moet blijven lopen.
     */
    public static function trySend(string $subject, string $html): bool
    {
        try {
            self::send($subject, $html);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
