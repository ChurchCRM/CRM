<?php

namespace ChurchCRM\Service;

/**
 * One-shot flash for the main app. The next page reads it once and it is gone.
 * Legacy pages still use the same two session keys directly.
 */
class FlashService
{
    private const MESSAGE_KEY = 'sGlobalMessage';
    private const CLASS_KEY = 'sGlobalMessageClass';

    public static function set(string $message, string $class = 'success'): void
    {
        self::ensureSession();
        $_SESSION[self::MESSAGE_KEY] = $message;
        $_SESSION[self::CLASS_KEY] = $class;
    }

    /**
     * @return array{message: string, class: string}|null
     */
    public static function take(): ?array
    {
        self::ensureSession();
        if (!isset($_SESSION[self::MESSAGE_KEY])) {
            return null;
        }

        $message = $_SESSION[self::MESSAGE_KEY];
        $class = $_SESSION[self::CLASS_KEY] ?? 'success';
        unset($_SESSION[self::MESSAGE_KEY], $_SESSION[self::CLASS_KEY]);

        return [
            'message' => $message,
            'class' => $class,
        ];
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
