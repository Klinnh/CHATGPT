<?php
header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../helpers/auth_helper.php';

session_start();

// Vérification des permissions
try {
    checkPageAccess('configuration');
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé'
    ]);
    exit;
}

// Récupération des données JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['role']) || !isset($data['page_code']) || !isset($data['has_access'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Données manquantes'
    ]);
    exit;
}

try {
    $db = (new Database())->getConnection();
    
    // Vérifier si la permission existe déjà
    $check_query = "SELECT COUNT(*) as count FROM role_permissions WHERE role = :role AND page_code = :page_code";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([
        ':role' => $data['role'],
        ':page_code' => $data['page_code']
    ]);
    $exists = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

    if ($exists) {
        // Mise à jour
        $query = "UPDATE role_permissions SET has_access = :has_access WHERE role = :role AND page_code = :page_code";
    } else {
        // Insertion
        $query = "INSERT INTO role_permissions (role, page_code, has_access) VALUES (:role, :page_code, :has_access)";
    }

    $stmt = $db->prepare($query);
    $success = $stmt->execute([
        ':role' => $data['role'],
        ':page_code' => $data['page_code'],
        ':has_access' => $data['has_access']
    ]);

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Permission mise à jour avec succès' : 'Erreur lors de la mise à jour'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur : ' . $e->getMessage()
    ]);
} 