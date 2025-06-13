<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

try {
    $db = new PDO(
        "mysql:host=localhost;port=3307;dbname=gestion_heures_supp",
        "root",
        "",
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4")
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connexion réussie à la base de données\n";
    
    // Récupération des paramètres de temps
    $stmt = $db->query("SELECT * FROM parametres_temps WHERE code = 'heures_semaine'");
    $parametres = $stmt->fetch(PDO::FETCH_ASSOC);
    $heures_standard = $parametres ? floatval($parametres['valeur']) : 35;
    echo "Heures standard par semaine : $heures_standard\n";

    // Récupération des utilisateurs techniciens
    $stmt = $db->query("SELECT id, nom, prenom FROM users WHERE role = 'technicien'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Nombre d'utilisateurs techniciens trouvés : " . count($users) . "\n";

    // Pour l'année et la semaine en cours
    $annee = date('Y');
    $semaine = date('W');
    $dateDebut = new DateTime();
    $dateDebut->setISODate($annee, $semaine);
    $dateFin = clone $dateDebut;
    $dateFin->modify('+6 days');
    
    echo "Période : du " . $dateDebut->format('Y-m-d') . " au " . $dateFin->format('Y-m-d') . "\n";
    
    // Pour chaque utilisateur
    foreach ($users as $user) {
        echo "\nTraitement de l'utilisateur {$user['nom']} {$user['prenom']} (ID: {$user['id']})\n";
        
        // Récupération des heures pour la semaine
        $query = "SELECT 
            COALESCE(SUM(CASE WHEN type_temps = 'heure_supplementaire' THEN duree_calculee ELSE 0 END), 0) as heures_supplementaires,
            COALESCE(SUM(CASE WHEN type_temps = 'recuperation' THEN duree_calculee ELSE 0 END), 0) as heures_recuperation,
            COALESCE(SUM(CASE WHEN type_temps = 'heure_supplementaire' THEN majoration_standard ELSE 0 END), 0) as majoration_25,
            COALESCE(SUM(CASE WHEN type_temps = 'heure_supplementaire' THEN majoration_superieur ELSE 0 END), 0) as majoration_50
        FROM heures_supplementaires 
        WHERE user_id = :user_id 
        AND date_jour BETWEEN :date_debut AND :date_fin
        AND statut = :statut";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':user_id' => $user['id'],
            ':date_debut' => $dateDebut->format('Y-m-d'),
            ':date_fin' => $dateFin->format('Y-m-d'),
            ':statut' => 'validé'
        ]);
        $heures = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Heures trouvées pour la semaine :\n";
        print_r($heures);

        // Calcul du cumul annuel
        $queryCumul = "SELECT 
            COALESCE(SUM(CASE WHEN type_temps = 'heure_supplementaire' THEN duree_calculee ELSE -duree_calculee END), 0) as cumul
        FROM heures_supplementaires 
        WHERE user_id = :user_id 
        AND YEAR(date_jour) = :annee 
        AND date_jour <= :date_fin
        AND statut = :statut";

        $stmt = $db->prepare($queryCumul);
        $stmt->execute([
            ':user_id' => $user['id'],
            ':annee' => $annee,
            ':date_fin' => $dateFin->format('Y-m-d'),
            ':statut' => 'validé'
        ]);
        $cumul = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Cumul annuel : {$cumul['cumul']}\n";

        // Calcul des heures travaillées
        $heures_travaillees = $heures_standard + $heures['heures_supplementaires'] - $heures['heures_recuperation'];
        echo "Heures travaillées calculées : $heures_travaillees\n";

        // Suppression des enregistrements existants
        $stmt = $db->prepare("DELETE FROM heures_brutes WHERE user_id = ? AND semaine = ? AND annee = ?");
        $stmt->execute([$user['id'], $semaine, $annee]);
        echo "Suppression des anciens enregistrements effectuée\n";

        // Insertion dans heures_brutes
        $queryInsert = "INSERT INTO heures_brutes (
            user_id, semaine, annee, 
            heures_travaillees, heures_standard, 
            heures_supplementaires, heures_recuperation,
            majoration_25, majoration_50, majoration_100,
            cumul_annuel, date_debut, date_fin
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($queryInsert);
        $stmt->execute([
            $user['id'],
            $semaine,
            $annee,
            $heures_travaillees,
            $heures_standard,
            $heures['heures_supplementaires'],
            $heures['heures_recuperation'],
            $heures['majoration_25'],
            $heures['majoration_50'],
            0, // majoration_100
            $cumul['cumul'],
            $dateDebut->format('Y-m-d'),
            $dateFin->format('Y-m-d')
        ]);
        echo "Données insérées avec succès\n";
    }

    echo "\nRemplissage de la table heures_brutes terminé avec succès.\n";

} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
} 