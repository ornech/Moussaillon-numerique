<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Application\Auth;

use PDO;

/**
 * Limite les essais de connexion : un PIN à 4 chiffres se devine vite sans ce garde-fou.
 */
final class LoginThrottle
{
    private const FENETRE_MINUTES = 15;
    private const MAX_PAR_PSEUDO = 5;
    private const MAX_PAR_IP = 30;

    public function __construct(
        private PDO $pdo
    ) {
    }

    public function estBloque(string $username, string $ip): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                SUM(username = ?) AS par_pseudo,
                SUM(ip = ?) AS par_ip
             FROM login_attempts
             WHERE attempted_at > NOW() - INTERVAL ' . self::FENETRE_MINUTES . ' MINUTE
               AND (username = ? OR ip = ?)'
        );
        $stmt->execute([$username, $ip, $username, $ip]);
        $compte = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) $compte['par_pseudo'] >= self::MAX_PAR_PSEUDO
            || (int) $compte['par_ip'] >= self::MAX_PAR_IP;
    }

    public function echec(string $username, string $ip): void
    {
        $this->pdo->prepare('INSERT INTO login_attempts (username, ip) VALUES (?, ?)')
            ->execute([mb_substr($username, 0, 50), $ip]);

        // Ménage : on ne garde pas d'historique au-delà d'une journée
        $this->pdo->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
    }

    public function reussite(string $username): void
    {
        $this->pdo->prepare('DELETE FROM login_attempts WHERE username = ?')->execute([$username]);
    }

    public static function minutesAttente(): int
    {
        return self::FENETRE_MINUTES;
    }
}
