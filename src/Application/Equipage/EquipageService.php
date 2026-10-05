<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Application\Equipage;

use Jf\Moussaillons\Application\ErreurMetier;
use PDO;

/**
 * Équipages : un adulte (parent ou enseignant) y regroupe ses moussaillons.
 * Un adulte ne voit et ne gère que ses équipages ; l'amirauté (admin) voit tout.
 */
final class EquipageService
{
    // Sans 0/O, 1/I/L : un enfant doit pouvoir recopier le code sans se tromper
    private const ALPHABET_CODE = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const LONGUEUR_CODE = 6;

    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * @return array{id: int, name: string, code: string}
     */
    public function creer(int $ownerId, string $nom): array
    {
        $nom = trim($nom);
        if (mb_strlen($nom) < 2 || mb_strlen($nom) > 60) {
            throw new ErreurMetier("Le nom de l'équipage doit faire entre 2 et 60 caractères.");
        }

        $code = $this->genererCode();
        $this->pdo->prepare('INSERT INTO crews (name, code, owner_id) VALUES (?, ?, ?)')
            ->execute([$nom, $code, $ownerId]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'name' => $nom, 'code' => $code];
    }

    public function trouverParCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, code FROM crews WHERE code = ?');
        $stmt->execute([self::normaliserCode($code)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Équipages visibles par un adulte, avec leur nombre de moussaillons.
     */
    public function listerPour(int $staffId, bool $estAdmin): array
    {
        $sql = 'SELECT c.id, c.name, c.code, c.owner_id, s.username AS owner_name, COUNT(u.id) AS nb_moussaillons
                FROM crews c
                JOIN staff s ON s.id = c.owner_id
                LEFT JOIN users u ON u.crew_id = c.id
                ' . ($estAdmin ? '' : 'WHERE c.owner_id = :staff') . '
                GROUP BY c.id
                ORDER BY (c.owner_id = :staff2) DESC, c.created_at ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($estAdmin ? ['staff2' => $staffId] : ['staff' => $staffId, 'staff2' => $staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function peutGererEquipage(int $crewId, int $staffId, bool $estAdmin): bool
    {
        $stmt = $this->pdo->prepare('SELECT owner_id FROM crews WHERE id = ?');
        $stmt->execute([$crewId]);
        $owner = $stmt->fetchColumn();
        return $owner !== false && ($estAdmin || (int) $owner === $staffId);
    }

    public function peutGererMoussaillon(int $userId, int $staffId, bool $estAdmin): bool
    {
        if ($estAdmin) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM users u JOIN crews c ON c.id = u.crew_id WHERE u.id = ? AND c.owner_id = ?'
        );
        $stmt->execute([$userId, $staffId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Crée un moussaillon dans un équipage. Retourne son id.
     */
    public function ajouterMoussaillon(int $crewId, string $pseudo, string $pin): int
    {
        $pseudo = self::validerPseudo($pseudo);
        self::validerPin($pin);

        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([$pseudo]);
        if ($stmt->fetchColumn()) {
            throw new ErreurMetier("Le pseudo « $pseudo » est déjà pris. Essaie par exemple « {$pseudo}" . random_int(2, 99) . ' ».');
        }

        $this->pdo->prepare('INSERT INTO users (username, pin_hash, current_ship_id, crew_id) VALUES (?, ?, 1, ?)')
            ->execute([$pseudo, password_hash($pin, PASSWORD_DEFAULT), $crewId]);

        return (int) $this->pdo->lastInsertId();
    }

    public function changerPin(int $userId, string $pin): void
    {
        self::validerPin($pin);
        $this->pdo->prepare('UPDATE users SET pin_hash = ? WHERE id = ?')
            ->execute([password_hash($pin, PASSWORD_DEFAULT), $userId]);
    }

    /**
     * Accepte "k7m-q4p", "K7M Q4P"… : seuls les lettres et chiffres comptent, en majuscules.
     */
    public static function normaliserCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public static function validerPseudo(string $pseudo): string
    {
        $pseudo = trim($pseudo);
        if (!preg_match('/^[\p{L}\p{N}_-]{3,20}$/u', $pseudo)) {
            throw new ErreurMetier('Le pseudo doit faire de 3 à 20 lettres ou chiffres, sans espace.');
        }
        return $pseudo;
    }

    public static function validerPin(string $pin): void
    {
        if (!preg_match('/^\d{4}$/', $pin)) {
            throw new ErreurMetier('Le code secret doit contenir exactement 4 chiffres.');
        }
    }

    private function genererCode(): string
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM crews WHERE code = ?');
        do {
            $code = '';
            for ($i = 0; $i < self::LONGUEUR_CODE; $i++) {
                $code .= self::ALPHABET_CODE[random_int(0, strlen(self::ALPHABET_CODE) - 1)];
            }
            $stmt->execute([$code]);
        } while ($stmt->fetchColumn());

        return $code;
    }
}
