<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Infrastructure;

/**
 * Configuration centralisée (remplace les define() dispersés).
 * Lit les variables d'environnement avec des valeurs par défaut.
 */
final class Config
{
    public static function dbHost(): string
    {
        return getenv('DB_HOST') ?: 'localhost';
    }

    public static function dbName(): string
    {
        return getenv('DB_NAME') ?: 'moussaillons';
    }

    public static function dbUser(): string
    {
        return getenv('DB_USER') ?: 'admin';
    }

    public static function dbPass(): string
    {
        return getenv('DB_PASS') ?: 'admin';
    }

    public static function pointsParQuiz(): int
    {
        return (int) (getenv('POINTS_PAR_QUIZ') ?: 10);
    }

    public static function maintenanceMode(): bool
    {
        return filter_var(getenv('MAINTENANCE_MODE'), FILTER_VALIDATE_BOOLEAN);
    }
}
