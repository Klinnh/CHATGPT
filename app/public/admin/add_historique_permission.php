<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/config/database.php';

try {
    $db = (new Database())->getConnection();

    // Ajout de la permission pour la page historique
    $query = "INSERT INTO role_permissions (role, page_code, has_access) 
              VALUES ('admin', 'historique', 1)
              ON DUPLICATE KEY UPDATE has_access = 1";

    $db->exec($query);
    echo "Permission d'accès à la page historique ajoutée avec succès pour les administrateurs.";

} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
    error_log("Erreur dans add_historique_permission.php: " . $e->getMessage());
} 