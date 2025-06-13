<?php
// Affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définition des constantes
define('ROOT_PATH', dirname(dirname(__DIR__)));
define('APP_PATH', ROOT_PATH);

// Démarrage de la session
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Vérification du rôle
if ($_SESSION['user_role'] !== 'administratif' && $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Inclusion des fichiers nécessaires
require_once APP_PATH . '/config/database.php';

// Vérification des données POST
if (!isset($_POST['declaration_id']) || !isset($_POST['commentaire'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$declaration_id = intval($_POST['declaration_id']);
$commentaire = trim($_POST['commentaire']);

if (empty($commentaire)) {
    echo json_encode(['success' => false, 'message' => 'Le commentaire ne peut pas être vide']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    
    // Vérifier que la déclaration existe et est en attente
    $check_query = "SELECT id FROM heures_supplementaires WHERE id = ? AND statut = 'en_attente'";
    $stmt = $db->prepare($check_query);
    $stmt->execute([$declaration_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Déclaration introuvable ou déjà traitée'
        ]);
        exit;
    }
    
    // Mise à jour de la déclaration avec le commentaire
    $update_query = "UPDATE heures_supplementaires 
                    SET commentaire_administratif = :commentaire,
                        commentaire_date = NOW(),
                        commentaire_user_id = :user_id
                    WHERE id = :declaration_id";

    $stmt = $db->prepare($update_query);
    $success = $stmt->execute([
        ':commentaire' => $commentaire,
        ':user_id' => $_SESSION['user_id'],
        ':declaration_id' => $declaration_id
    ]);
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'ajout du commentaire'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur : ' . $e->getMessage()
    ]);
} 