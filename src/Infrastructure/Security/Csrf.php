<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Infrastructure\Security;

/**
 * Jeton anti-CSRF stocké en session.
 * Les pages l'exposent via <meta name="csrf-token">, le JS le renvoie dans l'en-tête X-CSRF-Token.
 */
final class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Retourne le jeton de la session (le crée au premier appel).
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function isValid(?string $token): bool
    {
        return !empty($_SESSION[self::SESSION_KEY])
            && is_string($token)
            && hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * Balise à placer dans le <head> des pages qui appellent l'API.
     */
    public static function metaTag(): string
    {
        return '<meta name="csrf-token" content="' . Xss::escape(self::token()) . '">';
    }
}
