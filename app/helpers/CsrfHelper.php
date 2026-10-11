<?php

class CsrfHelper
{
    private const SESSION_KEY = 'csrf_token';
    public const FIELD_NAME = 'csrf_token';

    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verifyPost(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        $postedToken = $_POST[self::FIELD_NAME] ?? '';

        return is_string($sessionToken)
            && is_string($postedToken)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $postedToken);
    }
}
