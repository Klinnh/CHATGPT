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
    
    $user_id = 2; // ID de TALLEUR Adrien
    
    $query = "SELECT 
        hs.*,
        u.nom,
        u.prenom
    FROM heures_supplementaires hs
    JOIN users u ON hs.user_id = u.id
    WHERE hs.user_id = :user_id
    ORDER BY hs.date_jour";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $heures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nHeures supplémentaires pour {$heures[0]['nom']} {$heures[0]['prenom']} :\n\n";
    
    foreach ($heures as $heure) {
        echo "Date : {$heure['date_jour']}\n";
        echo "Type : {$heure['type_temps']}\n";
        echo "Durée calculée : {$heure['duree_calculee']}\n";
        echo "Majoration standard : {$heure['majoration_standard']}\n";
        echo "Majoration supérieure : {$heure['majoration_superieur']}\n";
        echo "Statut : {$heure['statut']}\n";
        echo "----------------------------------------\n";
    }
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
} 