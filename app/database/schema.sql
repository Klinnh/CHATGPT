-- Création de la base de données
CREATE DATABASE IF NOT EXISTS heures_supp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE heures_supp_db;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'user', 'administratif') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des permissions par rôle
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    page_code VARCHAR(100) NOT NULL,
    has_access BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_page (role, page_code)
) ENGINE=InnoDB;

-- Insertion des permissions par défaut
INSERT INTO role_permissions (role, page_code, has_access) VALUES
('admin', 'statistiques', true),
('admin', 'configuration', true),
('admin', 'users', true),
('administratif', 'statistiques', false),
('administratif', 'validation', true),
('manager', 'validation', true);

-- Table des heures supplémentaires
CREATE TABLE IF NOT EXISTS heures_supplementaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    duree DECIMAL(4,2) NOT NULL,
    type_temps ENUM('heure_supplementaire', 'recuperation') NOT NULL,
    description TEXT NOT NULL,
    status ENUM('en_attente', 'validé', 'rejeté') NOT NULL DEFAULT 'en_attente',
    validated_by INT,
    validated_at TIMESTAMP NULL,
    rejected_by INT,
    rejected_at TIMESTAMP NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (validated_by) REFERENCES users(id),
    FOREIGN KEY (rejected_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Insertion d'un utilisateur administrateur par défaut
INSERT INTO users (nom, prenom, email, password, role) 
VALUES ('Admin', 'System', 'admin@msi2000.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Le mot de passe par défaut est 'password'

CREATE TABLE IF NOT EXISTS cumul_semaine (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    semaine INT NOT NULL,
    annee INT NOT NULL,
    total_hs DECIMAL(5,2) DEFAULT 0,
    total_recup DECIMAL(5,2) DEFAULT 0,
    solde DECIMAL(5,2) DEFAULT 0,
    date_calcul TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES users(id),
    UNIQUE KEY unique_cumul (utilisateur_id, semaine, annee)
); 