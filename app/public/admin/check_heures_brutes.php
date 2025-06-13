<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = new PDO(
        "mysql:host=localhost;port=3307;dbname=gestion_heures_supp",
        "root",
        "",
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4")
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connexion réussie à la base de données\n";
    
    // Vérification de l'existence de la table
    $stmt = $db->query("SHOW TABLES LIKE 'heures_brutes'");
    if ($stmt->rowCount() > 0) {
        echo "La table heures_brutes existe.\n";
        
        // Nombre total d'enregistrements
        $stmt = $db->query("SELECT COUNT(*) as total FROM heures_brutes");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "Nombre total d'enregistrements : $total\n";
        
        // Récupération des enregistrements pour la semaine en cours
        $semaine = date('W');
        $annee = date('Y');
        
        echo "\nEnregistrements pour la semaine $semaine de $annee :\n";
        
        $query = "SELECT hb.*, u.nom, u.prenom 
            FROM heures_brutes hb
            JOIN users u ON hb.user_id = u.id
            WHERE hb.semaine = ? AND hb.annee = ?";
            
        $stmt = $db->prepare($query);
        $stmt->execute([$semaine, $annee]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "\nUtilisateur : {$row['nom']} {$row['prenom']}\n";
            echo "Semaine : {$row['semaine']}, Année : {$row['annee']}\n";
            echo "Période : du {$row['date_debut']} au {$row['date_fin']}\n";
            echo "Heures travaillées : {$row['heures_travaillees']}\n";
            echo "Heures standard : {$row['heures_standard']}\n";
            echo "Heures supplémentaires : {$row['heures_supplementaires']}\n";
            echo "Heures de récupération : {$row['heures_recuperation']}\n";
            echo "Majoration 25% : {$row['majoration_25']}\n";
            echo "Majoration 50% : {$row['majoration_50']}\n";
            echo "Majoration 100% : {$row['majoration_100']}\n";
            echo "Cumul annuel : {$row['cumul_annuel']}\n";
            echo "----------------------------------------\n";
        }
    } else {
        echo "La table heures_brutes n'existe pas.\n";
    }
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
} 