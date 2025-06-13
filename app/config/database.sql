-- Suppression des index s'ils existent
DROP INDEX IF EXISTS idx_user_date ON heures_supplementaires;
DROP INDEX IF EXISTS idx_statut ON heures_supplementaires;

-- Table des heures supplémentaires
CREATE TABLE IF NOT EXISTS heures_supplementaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date_heure DATETIME NOT NULL,
    nombre_heures DECIMAL(4,1) NOT NULL,
    motif ENUM('surcharge', 'urgence', 'remplacement', 'projet', 'autre') NOT NULL,
    statut ENUM('en_attente', 'validé', 'rejeté') NOT NULL DEFAULT 'en_attente',
    commentaire TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, date_heure),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 