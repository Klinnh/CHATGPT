-- Création de la table cumuls_semaine
CREATE TABLE IF NOT EXISTS cumuls_semaine (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    annee INT NOT NULL,
    semaine INT NOT NULL,
    heures_sup_brutes DECIMAL(10,2) NOT NULL DEFAULT 0,
    heures_recup_brutes DECIMAL(10,2) NOT NULL DEFAULT 0,
    heures_sup_apres_recup DECIMAL(10,2) NOT NULL DEFAULT 0,
    heures_maj_n1 DECIMAL(10,2) NOT NULL DEFAULT 0,
    heures_maj_n2 DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_heures_sup DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_heures_recup DECIMAL(10,2) NOT NULL DEFAULT 0,
    solde_semaine DECIMAL(10,2) NOT NULL DEFAULT 0,
    nb_declarations INT NOT NULL DEFAULT 0,
    hash_verification VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_semaine (user_id, annee, semaine)
); 