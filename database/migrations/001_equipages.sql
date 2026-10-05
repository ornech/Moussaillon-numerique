-- =============================================================================
-- MIGRATION 001 : ÉQUIPAGES (multi-utilisateur parents / enseignants)
-- Un adulte (parent ou enseignant) possède un ou plusieurs équipages.
-- Chaque moussaillon appartient à un équipage ; l'adulte ne voit que les siens.
-- Exécution : mysql -u<user> -p moussaillons < database/migrations/001_equipages.sql
-- =============================================================================

-- Profil de l'adulte (sert au vocabulaire affiché : "vos enfants" / "vos élèves")
ALTER TABLE staff
    ADD COLUMN profil ENUM('parent','enseignant') DEFAULT NULL AFTER role,
    ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE crews (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(60) NOT NULL,
    code VARCHAR(10) NOT NULL COMMENT 'Code à partager pour rejoindre l''équipage',
    owner_id INT(11) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY code (code),
    KEY owner_id (owner_id),
    CONSTRAINT crews_ibfk_1 FOREIGN KEY (owner_id) REFERENCES staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE users
    ADD COLUMN crew_id INT(11) DEFAULT NULL AFTER class_code,
    ADD KEY crew_id (crew_id),
    ADD CONSTRAINT users_ibfk_crew FOREIGN KEY (crew_id) REFERENCES crews (id) ON DELETE SET NULL;

-- Invitations à créer un compte adulte (lien à usage unique, émis par l'amirauté)
CREATE TABLE invitations (
    id INT(11) NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL COMMENT 'SHA-256 du jeton : le jeton lui-même n''est jamais stocké',
    created_by INT(11) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    used_by INT(11) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY token_hash (token_hash),
    CONSTRAINT invitations_ibfk_1 FOREIGN KEY (created_by) REFERENCES staff (id) ON DELETE CASCADE,
    CONSTRAINT invitations_ibfk_2 FOREIGN KEY (used_by) REFERENCES staff (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Échecs de connexion (limite les essais de PIN / mot de passe)
CREATE TABLE login_attempts (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY username_date (username, attempted_at),
    KEY ip_date (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Reprise de l'existant : les moussaillons déjà inscrits rejoignent un équipage de l'amirauté
INSERT INTO crews (name, code, owner_id)
SELECT 'Équipage de l''Amirauté', 'AMIRAL', id FROM staff WHERE role = 'admin' ORDER BY id LIMIT 1;

UPDATE users SET crew_id = (SELECT id FROM crews WHERE code = 'AMIRAL') WHERE crew_id IS NULL;
