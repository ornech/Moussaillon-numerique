<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Infrastructure;

use PDO;
use PDOException;

/**
 * Connexion PDO à la base MariaDB (driver MySQL).
 * Singleton : une seule instance partagée par requête.
 */
final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retourne l'instance unique. Utilise Config pour les paramètres de connexion.
     *
     * @throws PDOException Si la connexion échoue
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = self::createFromConstants();
        }

        return self::$instance;
    }

    /**
     * Crée une instance à partir de Config (variables d'environnement ou valeurs par défaut).
     *
     * @throws PDOException
     */
    private static function createFromConstants(): self
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            Config::dbHost(),
            Config::dbName()
        );
        $user = Config::dbUser();
        $pass = Config::dbPass();

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);

        // Options recommandées pour MariaDB/MySQL
        $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        return new self($pdo);
    }

    /**
     * Injecte une instance (tests, bootstrap). À appeler avant getInstance().
     */
    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /** Empêche le clonage du Singleton. */
    private function __clone() {}

    /** Empêche la désérialisation du Singleton. */
    public function __wakeup(): void
    {
        throw new \BadMethodCallException('Cannot unserialize singleton.');
    }
}
