<?php

/**
 * Vérifie si l'utilisateur est connecté
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur a la permission pour une page donnée
 */
function hasPermission($page_code) {
    if (!isUserLoggedIn()) {
        return false;
    }

    $db = (new Database())->getConnection();
    $query = "SELECT has_access FROM role_permissions 
              WHERE role = :role AND page_code = :page_code";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':role' => $_SESSION['user_role'],
        ':page_code' => $page_code
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result && $result['has_access'];
}

/**
 * Vérifie l'accès à une page et redirige si non autorisé
 */
function checkPageAccess($page_code) {
    if (!isUserLoggedIn()) {
        header('Location: /login.php');
        exit;
    }

    if (!hasPermission($page_code)) {
        header('Location: /login.php?error=unauthorized');
        exit;
    }
} 