<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/config/database.php';

try {
    $db = new PDO(
        "mysql:host=localhost;port=3307;dbname=gestion_heures_supp",
        "root",
        "",
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4")
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connexion réussie à la base de données\n";
    
    // Vérification des données pour la semaine en cours
    $annee = date('Y');
    $semaine = date('W');
    $dateDebut = new DateTime();
    $dateDebut->setISODate($annee, $semaine);
    $dateFin = clone $dateDebut;
    $dateFin->modify('+6 days');
    
    echo "Vérification des données pour la semaine $semaine de $annee\n";
    echo "Période : du " . $dateDebut->format('Y-m-d') . " au " . $dateFin->format('Y-m-d') . "\n\n";
    
    $query = "SELECT 
        hs.*,
        u.nom,
        u.prenom
    FROM heures_supplementaires hs
    JOIN users u ON hs.user_id = u.id
    WHERE hs.date_jour BETWEEN :date_debut AND :date_fin
    ORDER BY hs.date_jour, u.nom, u.prenom";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':date_debut' => $dateDebut->format('Y-m-d'),
        ':date_fin' => $dateFin->format('Y-m-d')
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "Aucune donnée trouvée pour cette période\n";
    } else {
        foreach ($results as $row) {
            echo "Utilisateur : {$row['nom']} {$row['prenom']}\n";
            echo "Date : {$row['date_jour']}\n";
            echo "Type : {$row['type_temps']}\n";
            echo "Durée : {$row['duree_calculee']}\n";
            echo "Majoration standard : {$row['majoration_standard']}\n";
            echo "Majoration supérieure : {$row['majoration_superieur']}\n";
            echo "Statut : {$row['statut']}\n";
            echo "----------------------------------------\n";
        }
    }
    
    // Vérification des paramètres
    echo "\nParamètres de temps :\n";
    $stmt = $db->query("SELECT * FROM parametres_temps");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['code']} : {$row['valeur']} ({$row['description']})\n";
    }
    
    // Vérification de la structure de la table heures_brutes
    echo "\nStructure de la table heures_brutes :\n";
    $stmt = $db->query("DESCRIBE heures_brutes");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} : {$row['Type']}\n";
    }
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
} 