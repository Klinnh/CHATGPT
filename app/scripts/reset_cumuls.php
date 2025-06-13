<?php
// Définition des constantes
define('ROOT_PATH', dirname(dirname(__DIR__)));
define('APP_PATH', ROOT_PATH . '/app');

// Inclusion des fichiers nécessaires
require_once APP_PATH . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    
    // Suppression de la table cumuls_semaine si elle existe
    $db->exec("DROP TABLE IF EXISTS cumuls_semaine");
    
    // Création de la table cumuls_semaine
    $db->exec("CREATE TABLE cumuls_semaine (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        annee INT NOT NULL,
        semaine INT NOT NULL,
        total_heures_sup DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_heures_maj_n1 DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_heures_maj_n2 DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_heures_recup DECIMAL(10,2) NOT NULL DEFAULT 0,
        solde_semaine DECIMAL(10,2) NOT NULL DEFAULT 0,
        nb_declarations INT NOT NULL DEFAULT 0,
        hash_verification VARCHAR(64) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        UNIQUE KEY unique_user_semaine (user_id, annee, semaine)
    )");

    // Création de la table cumuls_utilisateur si elle n'existe pas
    $db->exec("CREATE TABLE IF NOT EXISTS cumuls_utilisateur (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        total_heures_sup DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_heures_recup DECIMAL(10,2) NOT NULL DEFAULT 0,
        solde_actuel DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        UNIQUE KEY unique_user (user_id)
    )");

    // Fonction pour mettre à jour les cumuls utilisateur à partir des cumuls hebdomadaires
    $update_cumuls_query = "INSERT INTO cumuls_utilisateur (user_id, total_heures_sup, total_heures_recup, solde_actuel)
        SELECT 
            user_id,
            SUM(total_heures_sup) as total_heures_sup,
            SUM(total_heures_recup) as total_heures_recup,
            SUM(total_heures_sup) - SUM(total_heures_recup) as solde_actuel
        FROM cumuls_semaine
        GROUP BY user_id
        ON DUPLICATE KEY UPDATE
            total_heures_sup = VALUES(total_heures_sup),
            total_heures_recup = VALUES(total_heures_recup),
            solde_actuel = VALUES(solde_actuel)";
    
    $db->exec($update_cumuls_query);
    
    echo "Les tables cumuls_semaine et cumuls_utilisateur ont été créées avec succès.\n";
    echo "Vous pouvez maintenant utiliser le bouton 'Recalculer tous les cumuls' pour initialiser les données.\n";
    
} catch (PDOException $e) {
    echo "Erreur lors de la création des tables : " . $e->getMessage() . "\n";
} 