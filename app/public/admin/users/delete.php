<?php
// Affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définition des constantes
define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH);

// Démarrage de la session
session_start();

// Inclusion des fichiers nécessaires
require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/User.php';

// Vérification si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /login.php?error=unauthorized");
    exit();
}

// Vérification de la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /admin/users.php?error=invalid_method");
    exit();
}

// Vérification de l'ID utilisateur
if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
    header("Location: /admin/users.php?error=missing_id");
    exit();
}

$user_id = (int)$_POST['user_id'];

// Protection contre la suppression de son propre compte
if ($user_id === (int)$_SESSION['user_id']) {
    header("Location: /admin/users.php?error=cannot_delete_self");
    exit();
}

// Suppression de l'utilisateur
$database = new Database();
$db = $database->getConnection();
$user = new User($db);

if ($user->delete($user_id)) {
    header("Location: /admin/users.php?success=deleted");
} else {
    header("Location: /admin/users.php?error=delete_failed");
}
exit(); 