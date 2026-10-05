<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Application\Auth;

use Jf\Moussaillons\Application\Equipage\EquipageService;
use Jf\Moussaillons\Application\ErreurMetier;
use PDO;

/**
 * Comptes adultes (parents, enseignants) : uniquement sur invitation de l'amirauté.
 * L'invitation est un lien à usage unique, valable quelques jours.
 */
final class InscriptionAdulte
{
    private const VALIDITE_JOURS = 7;

    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * Crée une invitation et retourne le jeton à mettre dans le lien (seul son hash est stocké).
     */
    public function inviter(int $adminId): string
    {
        $jeton = bin2hex(random_bytes(24));
        $this->pdo->prepare(
            'INSERT INTO invitations (token_hash, created_by, expires_at)
             VALUES (?, ?, NOW() + INTERVAL ' . self::VALIDITE_JOURS . ' DAY)'
        )->execute([hash('sha256', $jeton), $adminId]);

        return $jeton;
    }

    public function invitationValide(string $jeton): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM invitations WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()'
        );
        $stmt->execute([hash('sha256', $jeton)]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Crée le compte adulte et son premier équipage, et consomme l'invitation. Retourne l'id du compte.
     */
    public function inscrire(
        string $jeton,
        string $profil,
        string $username,
        string $password,
        string $email,
        string $nomEquipage
    ): int {
        $username = trim($username);
        $email = trim($email);

        if (!in_array($profil, ['parent', 'enseignant'], true)) {
            throw new ErreurMetier('Indiquez si vous êtes parent ou enseignant.');
        }
        if (!preg_match('/^[\p{L}\p{N}._-]{3,50}$/u', $username)) {
            throw new ErreurMetier("L'identifiant doit faire de 3 à 50 caractères, sans espace.");
        }
        if (mb_strlen($password) < 8) {
            throw new ErreurMetier('Le mot de passe doit faire au moins 8 caractères.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ErreurMetier("L'adresse e-mail n'est pas valide (elle est facultative).");
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE invitations SET used_at = NOW()
                 WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()'
            );
            $stmt->execute([hash('sha256', $jeton)]);
            if ($stmt->rowCount() !== 1) {
                throw new ErreurMetier("Ce lien d'invitation n'est plus valide. Demandez-en un nouveau.");
            }

            $stmt = $this->pdo->prepare('SELECT 1 FROM staff WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetchColumn()) {
                throw new ErreurMetier("L'identifiant « $username » est déjà utilisé. Choisissez-en un autre.");
            }

            $this->pdo->prepare(
                "INSERT INTO staff (username, pin_hash, role, profil, email) VALUES (?, ?, 'teacher', ?, ?)"
            )->execute([$username, password_hash($password, PASSWORD_DEFAULT), $profil, $email ?: null]);
            $staffId = (int) $this->pdo->lastInsertId();

            $this->pdo->prepare('UPDATE invitations SET used_by = ? WHERE token_hash = ?')
                ->execute([$staffId, hash('sha256', $jeton)]);

            (new EquipageService($this->pdo))->creer($staffId, $nomEquipage);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $staffId;
    }

    public static function validiteJours(): int
    {
        return self::VALIDITE_JOURS;
    }
}
