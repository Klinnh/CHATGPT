<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Suppression de la table si elle existe
    $db->exec("DROP TABLE IF EXISTS cumul_heures_historique");

    $query = "
    CREATE TABLE cumul_heures_historique (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        date_calcul DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        annee INT NOT NULL,
        semaine INT NOT NULL,
        heures_supplementaires DECIMAL(10,2) DEFAULT 0,
        heures_recuperation DECIMAL(10,2) DEFAULT 0,
        majoration_25 DECIMAL(10,2) DEFAULT 0,
        majoration_50 DECIMAL(10,2) DEFAULT 0,
        cumul_final DECIMAL(10,2) DEFAULT 0,
        UNIQUE KEY unique_user_semaine (user_id, annee, semaine),
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $db->exec($query);
    echo "Table cumul_heures_historique créée avec succès.\n";

} catch (PDOException $e) {
    echo "Erreur lors de la création de la table : " . $e->getMessage() . "\n";
} 