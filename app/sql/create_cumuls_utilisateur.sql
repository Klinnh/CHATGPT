-- Création de la table cumuls_utilisateur
CREATE TABLE IF NOT EXISTS cumuls_utilisateur (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    total_heures_sup DECIMAL(6,2) DEFAULT 0.00,
    total_heures_maj_n1 DECIMAL(6,2) DEFAULT 0.00,
    total_heures_maj_n2 DECIMAL(6,2) DEFAULT 0.00,
    total_heures_recup DECIMAL(6,2) DEFAULT 0.00,
    solde_actuel DECIMAL(6,2) GENERATED ALWAYS AS 
        (total_heures_sup - total_heures_recup) STORED,
    derniere_semaine_calculee INT,
    derniere_annee_calculee INT,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user (user_id)
);

-- Initialisation des données pour chaque utilisateur
INSERT INTO cumuls_utilisateur (
    user_id,
    total_heures_sup,
    total_heures_maj_n1,
    total_heures_maj_n2,
    total_heures_recup,
    derniere_semaine_calculee,
    derniere_annee_calculee,
    updated_at
)
SELECT 
    h.user_id,
    COALESCE(SUM(CASE WHEN type_temps = 'heure_supplementaire' THEN duree_calculee ELSE 0 END), 0) as total_heures_sup,
    0 as total_heures_maj_n1,
    0 as total_heures_maj_n2,
    COALESCE(SUM(CASE WHEN type_temps = 'recuperation' THEN duree_calculee ELSE 0 END), 0) as total_heures_recup,
    WEEK(MAX(date_jour), 3) as derniere_semaine_calculee,
    YEAR(MAX(date_jour)) as derniere_annee_calculee,
    NOW() as updated_at
FROM heures_supplementaires h
WHERE h.statut = 'validé'
GROUP BY h.user_id; 