<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../models/User.php';
require_once '../../models/HeureSupplementaire.php';
require_once '../../models/CumulManager.php';

// Vérification de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../helpers/auth_helper.php';
checkPageAccess('historique');

// Désactiver l'affichage des erreurs pour garantir un JSON propre
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

try {
    $db = (new Database())->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Récupération des paramètres de l'URL
    $annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
    $mois = isset($_GET['mois']) ? (int)$_GET['mois'] : null;
    $semaine = isset($_GET['semaine']) ? (int)$_GET['semaine'] : null;
    $user_id = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;

    error_log("=== DÉBUT DU TRAITEMENT ===");
    error_log("Paramètres reçus: " . json_encode([
        'annee' => $annee,
        'mois' => $mois,
        'semaine' => $semaine,
        'user_id' => $user_id
    ]));

    // 2. Vérification de l'utilisateur (uniquement si un utilisateur spécifique est demandé)
    $user_result = null;
    if ($user_id) {
        $user_query = "SELECT id, nom, prenom, actif FROM users WHERE id = ? AND actif = 1";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->execute([$user_id]);
        $user_result = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Vérification utilisateur: " . ($user_result ? "Trouvé" : "Non trouvé"));
        if ($user_result) {
            error_log("Données utilisateur: " . json_encode($user_result));
        }

        if (!$user_result) {
            echo json_encode([
                'success' => false,
                'message' => "Utilisateur non trouvé ou inactif",
                'debug' => ['user_id' => $user_id]
            ]);
            exit;
        }
    }

    // Vérification des données existantes
    $check_query = "
        SELECT 
            hs.type_temps,
            COUNT(*) as total,
            MIN(hs.date_jour) as premiere_date,
            MAX(hs.date_jour) as derniere_date
        FROM heures_supplementaires hs
        WHERE 1=1";
    
    $check_params = [];
    
    if ($annee) {
        $check_query .= " AND YEAR(hs.date_jour) = ?";
        $check_params[] = $annee;
    }
    if ($mois) {
        $check_query .= " AND MONTH(hs.date_jour) = ?";
        $check_params[] = $mois;
    }
    if ($user_id) {
        $check_query .= " AND hs.user_id = ?";
        $check_params[] = $user_id;
    }
    
    $check_query .= " GROUP BY hs.type_temps";
    
    error_log("=== VÉRIFICATION DES DONNÉES ===");
    error_log("Requête de vérification: " . $check_query);
    error_log("Paramètres de vérification: " . json_encode($check_params));
    
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute($check_params);
    $check_results = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Données trouvées: " . json_encode($check_results));

    // Requête principale
    $heures_query = "
        SELECT 
            hs.id,
            hs.user_id,
            hs.date_jour,
            hs.type_temps,
            hs.duree_calculee,
            hs.statut,
            u.nom,
            u.prenom,
            WEEK(hs.date_jour, 3) as semaine,
            DATE_FORMAT(hs.date_jour, '%d/%m/%Y') as date_formatee
        FROM heures_supplementaires hs
        JOIN users u ON u.id = hs.user_id
        WHERE u.actif = 1
        AND hs.statut = 'validé'";

    $params = [];
    
    if ($user_id) {
        $heures_query .= " AND hs.user_id = ?";
        $params[] = $user_id;
    }
    
    if ($annee) {
        $heures_query .= " AND YEAR(hs.date_jour) = ?";
        $params[] = $annee;
    }
    
    if ($mois) {
        $heures_query .= " AND MONTH(hs.date_jour) = ?";
        $params[] = $mois;
    }
    
    if ($semaine) {
        $heures_query .= " AND WEEK(hs.date_jour, 3) = ?";
        $params[] = $semaine;
    }

    $heures_query .= " ORDER BY hs.date_jour ASC, u.nom, u.prenom";

    error_log("=== DEBUG REQUÊTE ===");
    error_log("Requête SQL: " . $heures_query);
    error_log("Paramètres: " . json_encode($params));

    $stmt = $db->prepare($heures_query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Nombre de résultats trouvés: " . count($results));

    // Traitement des résultats par semaine
    $totaux_par_semaine = [];
    foreach ($results as $row) {
        error_log("=== TRAITEMENT LIGNE ===");
        error_log("ID: " . $row['id']);
        error_log("User: " . $row['nom'] . " " . $row['prenom']);
        error_log("Date: " . $row['date_jour']);
        error_log("Type: " . $row['type_temps']);
        error_log("Durée: " . $row['duree_calculee']);
        error_log("Statut: " . $row['statut']);
        
        $semaine = $row['semaine'];
        $key = $row['user_id'] . '-' . $semaine;

        if (!isset($totaux_par_semaine[$key])) {
            $totaux_par_semaine[$key] = [
                'user_id' => $row['user_id'],
                'nom' => $row['nom'],
                'prenom' => $row['prenom'],
                'semaine' => $semaine,
                'jour' => $row['date_formatee'],
                'heures_supp' => 0.00,
                'recuperation' => 0.00,
                'majoration_25' => 0.00,
                'majoration_50' => 0.00,
                'nb_heures_semaine' => 39.00
            ];
        }

        $duree = floatval($row['duree_calculee']);

        // Traitement selon le type
        if (strtolower($row['type_temps']) === 'heure_supplementaire') {
            $totaux_par_semaine[$key]['heures_supp'] += $duree;
            error_log("Ajout heures supp: " . $duree . " - Total: " . $totaux_par_semaine[$key]['heures_supp']);
        } elseif (strtolower($row['type_temps']) === 'recuperation') {
            $totaux_par_semaine[$key]['recuperation'] += $duree;
            error_log("Ajout récupération: " . $duree . " - Total: " . $totaux_par_semaine[$key]['recuperation']);
        }

        // Calcul du solde (heures supp - récupération)
        $solde = $totaux_par_semaine[$key]['heures_supp'] - $totaux_par_semaine[$key]['recuperation'];
        $totaux_par_semaine[$key]['solde'] = $solde;

        // Calcul des majorations uniquement sur la partie positive du solde
        $solde_positif = max(0, $solde);
        if ($solde_positif > 0) {
            if ($solde_positif <= 4) {
                $totaux_par_semaine[$key]['majoration_25'] = $solde_positif * 0.25;
                $totaux_par_semaine[$key]['majoration_50'] = 0;
            } else {
                $totaux_par_semaine[$key]['majoration_25'] = 4 * 0.25;
                $totaux_par_semaine[$key]['majoration_50'] = ($solde_positif - 4) * 0.50;
            }
        } else {
            $totaux_par_semaine[$key]['majoration_25'] = 0;
            $totaux_par_semaine[$key]['majoration_50'] = 0;
        }

        error_log("Solde calculé: " . $solde);
        error_log("Majorations calculées: 25%=" . $totaux_par_semaine[$key]['majoration_25'] . ", 50%=" . $totaux_par_semaine[$key]['majoration_50']);

        error_log("Totaux pour " . $key . ": " . json_encode($totaux_par_semaine[$key]));
    }

    // Conversion en tableau pour le JSON
    $resultat_final = array_values($totaux_par_semaine);

    // Formatage des nombres pour l'affichage
    foreach ($resultat_final as &$ligne) {
        $ligne['heures_supp'] = number_format((float)$ligne['heures_supp'], 2, '.', '');
        $ligne['recuperation'] = number_format((float)$ligne['recuperation'], 2, '.', '');
        $ligne['solde'] = number_format((float)$ligne['solde'], 2, '.', '');
        $ligne['majoration_25'] = number_format((float)$ligne['majoration_25'], 2, '.', '');
        $ligne['majoration_50'] = number_format((float)$ligne['majoration_50'], 2, '.', '');
    }

    error_log("=== RÉSULTAT FINAL ===");
    error_log(json_encode($resultat_final));

    echo json_encode([
        'success' => true,
        'data' => $resultat_final
    ]);
} catch (Exception $e) {
    error_log("Erreur dans historique_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => "Une erreur est survenue lors du chargement des données: " . $e->getMessage()
    ]);
    exit;
} 