<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(dirname(dirname(dirname(__DIR__)))));
define('APP_PATH', ROOT_PATH . '/app');

session_start();
require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/Client.php';

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID manquant']);
    exit;
}

$db = (new Database())->getConnection();
$client = new Client($db);

$client_data = $client->readOne($_GET['id']);

if (!$client_data) {
    http_response_code(404);
    echo json_encode(['error' => 'Client non trouvé']);
    exit;
}

header('Content-Type: application/json');
echo json_encode($client_data); 