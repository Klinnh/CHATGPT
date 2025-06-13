<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = new PDO(
        "mysql:host=localhost;port=3307;dbname=gestion_heures_supp",
        "root",
        ""
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connexion réussie à la base de données\n";
    
    // Suppression de la table si elle existe
    $db->exec("DROP TABLE IF EXISTS heures_brutes");
    echo "Ancienne table supprimée si elle existait\n";
    
    // Création de la table
    $sql = "CREATE TABLE heures_brutes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        semaine INT,
        annee INT,
        heures_travaillees DECIMAL(5,2),
        heures_standard DECIMAL(5,2),
        heures_supplementaires DECIMAL(5,2),
        heures_recuperation DECIMAL(5,2),
        majoration_25 DECIMAL(5,2),
        majoration_50 DECIMAL(5,2),
        majoration_100 DECIMAL(5,2),
        cumul_annuel DECIMAL(5,2),
        date_debut DATE,
        date_fin DATE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $db->exec($sql);
    echo "Table heures_brutes créée avec succès\n";
    
    // Vérification
    $stmt = $db->query("SHOW TABLES LIKE 'heures_brutes'");
    if ($stmt->rowCount() > 0) {
        echo "La table existe bien dans la base de données\n";
        
        // Afficher la structure de la table
        $stmt = $db->query("DESCRIBE heures_brutes");
        echo "Structure de la table :\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "- {$row['Field']} : {$row['Type']}\n";
        }
    } else {
        echo "Erreur : La table n'a pas été créée\n";
    }
    
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
    echo "Code d'erreur : " . $e->getCode();
} 