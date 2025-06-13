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
    
    // Vérifier les paramètres de temps
    $stmt = $db->query("SELECT * FROM parametres_temps WHERE id = 1");
    $parametres = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Paramètres de temps :<br>";
    echo "- Heures semaine contractuelle : {$parametres['heures_semaine_contractuelle']}<br><br>";
    
    // Vérification des heures supplémentaires pour la semaine en cours
    $annee = date('Y');
    $semaine = date('W');
    $dateDebut = new DateTime();
    $dateDebut->setISODate($annee, $semaine);
    $dateFin = clone $dateDebut;
    $dateFin->modify('+6 days');
    
    error_log("Vérification des heures pour la semaine $semaine de $annee");
    error_log("Période : du " . $dateDebut->format('Y-m-d') . " au " . $dateFin->format('Y-m-d'));
    
    $query = "SELECT 
        u.id,
        u.nom,
        u.prenom,
        hs.type_temps,
        hs.date_jour,
        hs.duree_calculee,
        hs.majoration_standard,
        hs.majoration_superieur,
        hs.statut
    FROM users u
    LEFT JOIN heures_supplementaires hs ON u.id = hs.user_id
    WHERE u.role = 'technicien'
    AND (hs.date_jour BETWEEN :date_debut AND :date_fin OR hs.date_jour IS NULL)
    ORDER BY u.nom, u.prenom, hs.date_jour";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':date_debut' => $dateDebut->format('Y-m-d'),
        ':date_fin' => $dateFin->format('Y-m-d')
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        error_log("Aucune donnée trouvée pour cette période");
    } else {
        foreach ($results as $row) {
            error_log("Utilisateur : {$row['nom']} {$row['prenom']}");
            if ($row['date_jour']) {
                error_log("- Date : {$row['date_jour']}");
                error_log("- Type : {$row['type_temps']}");
                error_log("- Durée : {$row['duree_calculee']}");
                error_log("- Majorations : standard={$row['majoration_standard']}, supérieur={$row['majoration_superieur']}");
                error_log("- Statut : {$row['statut']}");
            } else {
                error_log("- Aucune heure enregistrée");
            }
            error_log("----------------------------------------");
        }
    }
    
} catch (PDOException $e) {
    error_log("Erreur : " . $e->getMessage());
    error_log("Code d'erreur : " . $e->getCode());
} 