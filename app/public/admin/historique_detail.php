<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/HeureSupplementaire.php';
require_once APP_PATH . '/helpers/auth_helper.php';

session_start();

// Vérification des permissions
checkPageAccess('historique');

try {
    $db = (new Database())->getConnection();

    // Récupération des paramètres
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $annee = isset($_GET['annee']) ? (int)$_GET['annee'] : date('Y');
    $mois = isset($_GET['mois']) ? (int)$_GET['mois'] : null;

    if (!$userId) {
        throw new Exception("ID utilisateur manquant");
    }

    // Récupération des informations de l'utilisateur
    $stmt = $db->prepare("SELECT nom, prenom, service FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Utilisateur non trouvé");
    }

    // Récupération des heures standard
    $stmt = $db->query("SELECT valeur FROM parametres_temps WHERE code = 'heures_semaine'");
    $heures_standard = $stmt->fetch(PDO::FETCH_ASSOC)['valeur'] ?? 35;

    // Calcul des dates de début et de fin
    if ($mois) {
        // Mode mensuel
        $date_debut = date('Y-m-d', strtotime("$annee-$mois-01"));
        $date_fin = date('Y-m-t', strtotime($date_debut));
    } else {
        // Mode annuel
        $date_debut = date('Y-m-d', strtotime("$annee-01-01"));
        $date_fin = date('Y-m-d', strtotime("$annee-12-31"));
    }

    // Récupération des heures détaillées par jour
    $query = "SELECT 
        date_jour,
        type_temps,
        duree_calculee,
        motif,
        commentaire,
        majoration_25,
        majoration_50,
        majoration_100
    FROM heures_supplementaires 
    WHERE user_id = :user_id 
    AND date_jour BETWEEN :date_debut AND :date_fin
    AND statut = 'validé'
    ORDER BY date_jour";

    $stmt = $db->prepare($query);
    $stmt->execute([
        ':user_id' => $userId,
        ':date_debut' => $date_debut,
        ':date_fin' => $date_fin
    ]);
    $heuresDetail = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organisation des données par jour
    $donneesParJour = [];
    $currentDate = strtotime($date_debut);
    $endDate = strtotime($date_fin);

    while ($currentDate <= $endDate) {
        $date = date('Y-m-d', $currentDate);
        $donneesParJour[$date] = [
            'heures_travaillees' => $heures_standard / 7, // Heures standard par jour
            'heures_supplementaires' => 0,
            'heures_recuperation' => 0,
            'majoration_25' => 0,
            'majoration_50' => 0,
            'majoration_100' => 0,
            'commentaire' => ''
        ];
        $currentDate = strtotime('+1 day', $currentDate);
    }

    // Remplissage des données par jour
    foreach ($heuresDetail as $detail) {
        $date = $detail['date_jour'];
        if (isset($donneesParJour[$date])) {
            if ($detail['type_temps'] === 'heure_supplementaire') {
                $donneesParJour[$date]['heures_supplementaires'] += $detail['duree_calculee'];
                $donneesParJour[$date]['heures_travaillees'] += $detail['duree_calculee'];
            } elseif ($detail['type_temps'] === 'recuperation') {
                $donneesParJour[$date]['heures_recuperation'] += $detail['duree_calculee'];
                $donneesParJour[$date]['heures_travaillees'] -= $detail['duree_calculee'];
            }

            $donneesParJour[$date]['majoration_25'] += $detail['majoration_25'];
            $donneesParJour[$date]['majoration_50'] += $detail['majoration_50'];
            $donneesParJour[$date]['majoration_100'] += $detail['majoration_100'];

            if ($detail['commentaire']) {
                $donneesParJour[$date]['commentaire'] .= $detail['commentaire'] . "\n";
            }
        }
    }

    echo json_encode([
        'success' => true,
        'user' => $user,
        'details' => $donneesParJour,
        'periode' => $mois ? date('F Y', strtotime($date_debut)) : $annee
    ]);

} catch (Exception $e) {
    error_log("Erreur dans historique_detail.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => "Une erreur est survenue lors de la récupération des données."
    ]);
} 