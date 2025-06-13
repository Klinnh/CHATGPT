<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Vérification de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../helpers/auth_helper.php';
checkPageAccess('historique');

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

try {
    // Test de la connexion à la base de données
    $db = (new Database())->getConnection();
    if (!$db) {
        throw new Exception("Impossible de se connecter à la base de données");
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Validation des paramètres
    if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
        throw new Exception('ID utilisateur invalide');
    }
    
    $user_id = (int)$_GET['user_id'];
    $annee = isset($_GET['annee']) && is_numeric($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
    
    // Validation du mois (null si vide ou "tous les mois")
    $mois = null;
    if (isset($_GET['mois']) && $_GET['mois'] !== '' && $_GET['mois'] !== 'null' && is_numeric($_GET['mois'])) {
        $mois = (int)$_GET['mois'];
    }
    
    // Validation de la semaine (null si vide ou "toutes les semaines")
    $semaine = null;
    if (isset($_GET['semaine']) && $_GET['semaine'] !== '' && $_GET['semaine'] !== 'null' && is_numeric($_GET['semaine'])) {
        $semaine = (int)$_GET['semaine'];
    }

    // Log des paramètres pour le débogage
    error_log("Paramètres reçus - user_id: $user_id, annee: $annee, mois: " . 
             (isset($_GET['mois']) ? $_GET['mois'] : 'non défini') . ", semaine: " . 
             (isset($_GET['semaine']) ? $_GET['semaine'] : 'non défini'));
    error_log("Paramètres traités - mois: " . ($mois !== null ? $mois : 'null') . 
             ", semaine: " . ($semaine !== null ? $semaine : 'null'));

    // Vérification de l'existence de l'utilisateur
    $check_user = $db->prepare("SELECT id FROM users WHERE id = :user_id");
    $check_user->execute([':user_id' => $user_id]);
    if (!$check_user->fetch()) {
        throw new Exception("Utilisateur non trouvé");
    }

    // Vérification de la structure de la table heures_supplementaires
    try {
        $check_structure = $db->query("DESCRIBE heures_supplementaires");
        $columns = $check_structure->fetchAll(PDO::FETCH_COLUMN);
        $required_columns = [
            'date_jour', 
            'type_temps', 
            'duree_calculee', 
            'client_id',
            'motif', 
            'statut', 
            'user_id',
            'heure_debut',
            'heure_fin',
            'temps_pause',
            'commentaire'
        ];
        $missing_columns = array_diff($required_columns, $columns);
        
        if (!empty($missing_columns)) {
            throw new Exception("Colonnes manquantes dans la table heures_supplementaires: " . implode(', ', $missing_columns));
        }
    } catch (PDOException $e) {
        throw new Exception("Erreur lors de la vérification de la structure de la table: " . $e->getMessage());
    }

    // Construction de la requête
    $query = "
        SELECT 
            DATE_FORMAT(hs.date_jour, '%Y-%m-%d') as date,
            hs.type_temps,
            hs.duree_calculee,
            COALESCE(c.nom, '-') as client,
            COALESCE(hs.motif, '') as motif,
            COALESCE(hs.statut, 'en_attente') as statut,
            hs.heure_debut,
            hs.heure_fin,
            COALESCE(hs.temps_pause, 0) as temps_pause,
            COALESCE(hs.commentaire, '') as commentaire
        FROM heures_supplementaires hs
        LEFT JOIN clients c ON hs.client_id = c.id
        WHERE hs.user_id = :user_id
        AND YEAR(hs.date_jour) = :annee";

    $params = [
        ':user_id' => $user_id,
        ':annee' => $annee
    ];

    if ($mois !== null) {
        $query .= " AND MONTH(hs.date_jour) = :mois";
        $params[':mois'] = $mois;
    }

    if ($semaine !== null) {
        $query .= " AND WEEK(hs.date_jour, 3) = :semaine";
        $params[':semaine'] = $semaine;
    }

    $query .= " ORDER BY hs.date_jour ASC, hs.heure_debut ASC";

    // Log de la requête pour le débogage
    error_log("Requête SQL: " . $query);
    error_log("Paramètres: " . print_r($params, true));

    $stmt = $db->prepare($query);
    
    // Bind des paramètres
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }

    if (!$stmt->execute()) {
        $error_info = $stmt->errorInfo();
        throw new Exception("Erreur d'exécution de la requête: " . $error_info[2]);
    }

    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'details' => $details,
        'debug' => [
            'params' => $params,
            'query' => $query,
            'row_count' => count($details)
        ]
    ]);

} catch (PDOException $e) {
    error_log("Erreur PDO dans historique_details.php: " . $e->getMessage());
    error_log("Code erreur: " . $e->getCode());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "Erreur de base de données",
        'debug' => [
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ]
    ]);
} catch (Exception $e) {
    error_log("Erreur dans historique_details.php: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 