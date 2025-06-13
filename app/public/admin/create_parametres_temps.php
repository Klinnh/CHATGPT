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
    error_log("Connexion réussie à la base de données");
    
    // Suppression de la table si elle existe
    $db->exec("DROP TABLE IF EXISTS parametres_temps");
    error_log("Ancienne table supprimée si elle existait");
    
    // Création de la table
    $sql = "CREATE TABLE parametres_temps (
        id INT PRIMARY KEY AUTO_INCREMENT,
        code VARCHAR(50) NOT NULL UNIQUE,
        valeur DECIMAL(5,2) NOT NULL,
        description TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $db->exec($sql);
    error_log("Table parametres_temps créée avec succès");
    
    // Insertion des paramètres par défaut
    $parametres = [
        [
            'code' => 'heures_semaine',
            'valeur' => 35.00,
            'description' => 'Nombre d\'heures standard par semaine'
        ]
    ];
    
    $stmt = $db->prepare("INSERT INTO parametres_temps (code, valeur, description) VALUES (:code, :valeur, :description)");
    
    foreach ($parametres as $param) {
        $stmt->execute($param);
        error_log("Paramètre {$param['code']} inséré avec succès");
    }
    
    // Vérification
    $stmt = $db->query("SELECT * FROM parametres_temps");
    error_log("\nParamètres insérés :");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        error_log("- {$row['code']} : {$row['valeur']} ({$row['description']})");
    }
    
} catch (PDOException $e) {
    error_log("Erreur : " . $e->getMessage());
    error_log("Code d'erreur : " . $e->getCode());
} 