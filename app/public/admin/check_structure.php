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
    
    // Vérification de la table heures_supplementaires
    $stmt = $db->query("SHOW TABLES LIKE 'heures_supplementaires'");
    if ($stmt->rowCount() > 0) {
        error_log("La table heures_supplementaires existe");
        $stmt = $db->query("DESCRIBE heures_supplementaires");
        error_log("Structure de la table heures_supplementaires :");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            error_log("- {$row['Field']} : {$row['Type']}");
        }
    } else {
        error_log("La table heures_supplementaires n'existe pas");
    }
    
    // Vérification de la table heures_brutes
    $stmt = $db->query("SHOW TABLES LIKE 'heures_brutes'");
    if ($stmt->rowCount() > 0) {
        error_log("\nLa table heures_brutes existe");
        $stmt = $db->query("DESCRIBE heures_brutes");
        error_log("Structure de la table heures_brutes :");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            error_log("- {$row['Field']} : {$row['Type']}");
        }
    } else {
        error_log("\nLa table heures_brutes n'existe pas");
    }
    
    // Vérification de la table parametres_temps
    $stmt = $db->query("SHOW TABLES LIKE 'parametres_temps'");
    if ($stmt->rowCount() > 0) {
        error_log("\nLa table parametres_temps existe");
        $stmt = $db->query("DESCRIBE parametres_temps");
        error_log("Structure de la table parametres_temps :");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            error_log("- {$row['Field']} : {$row['Type']}");
        }
    } else {
        error_log("\nLa table parametres_temps n'existe pas");
    }
    
} catch (PDOException $e) {
    error_log("Erreur : " . $e->getMessage());
    error_log("Code d'erreur : " . $e->getCode());
} 