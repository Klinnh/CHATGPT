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
    
    // Vérification de la table heures_supplementaires
    $stmt = $db->query("SELECT COUNT(*) as total FROM heures_supplementaires");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Nombre total d'enregistrements dans heures_supplementaires : $total\n";
    
    // Vérification des statuts
    $stmt = $db->query("SELECT DISTINCT statut FROM heures_supplementaires");
    echo "\nStatuts présents dans la table :\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . bin2hex($row['statut']) . " (hex)\n";
        echo "- " . $row['statut'] . " (texte)\n";
    }
    
    // Vérification des types de temps
    $stmt = $db->query("SELECT DISTINCT type_temps FROM heures_supplementaires");
    echo "\nTypes de temps présents dans la table :\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['type_temps'] . "\n";
    }
    
    // Vérification des heures pour la semaine en cours
    $annee = date('Y');
    $semaine = date('W');
    $dateDebut = new DateTime();
    $dateDebut->setISODate($annee, $semaine);
    $dateFin = clone $dateDebut;
    $dateFin->modify('+6 days');
    
    echo "\nVérification des heures pour la semaine $semaine de $annee\n";
    echo "Période : du " . $dateDebut->format('Y-m-d') . " au " . $dateFin->format('Y-m-d') . "\n";
    
    $query = "SELECT 
        u.id,
        u.nom,
        u.prenom,
        COUNT(*) as total_records,
        SUM(CASE WHEN type_temps = 'heure_supplementaire' THEN duree_calculee ELSE 0 END) as heures_supplementaires,
        SUM(CASE WHEN type_temps = 'recuperation' THEN duree_calculee ELSE 0 END) as heures_recuperation,
        SUM(CASE WHEN type_temps = 'heure_supplementaire' THEN majoration_standard ELSE 0 END) as majoration_25,
        SUM(CASE WHEN type_temps = 'heure_supplementaire' THEN majoration_superieur ELSE 0 END) as majoration_50
    FROM users u
    LEFT JOIN heures_supplementaires hs ON u.id = hs.user_id
    WHERE u.role = 'technicien'
    AND (hs.date_jour BETWEEN :date_debut AND :date_fin OR hs.date_jour IS NULL)
    GROUP BY u.id, u.nom, u.prenom";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':date_debut' => $dateDebut->format('Y-m-d'),
        ':date_fin' => $dateFin->format('Y-m-d')
    ]);
    
    echo "\nRésultats par utilisateur :\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "\nUtilisateur : {$row['nom']} {$row['prenom']} (ID: {$row['id']})\n";
        echo "Nombre d'enregistrements : {$row['total_records']}\n";
        echo "Heures supplémentaires : {$row['heures_supplementaires']}\n";
        echo "Heures de récupération : {$row['heures_recuperation']}\n";
        echo "Majoration 25% : {$row['majoration_25']}\n";
        echo "Majoration 50% : {$row['majoration_50']}\n";
    }
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
} 