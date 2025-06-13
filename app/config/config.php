<?php
// Configuration de base
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrage de la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration des constantes
define('BASE_URL', '/');
define('APP_NAME', 'Gestion des Heures Supplémentaires');

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'heures_supplementaires');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configuration des fuseaux horaires
date_default_timezone_set('Europe/Paris');

// Configuration des chemins
define('ROOT_PATH', dirname(dirname(__DIR__)));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', APP_PATH . '/public');
define('VIEWS_PATH', APP_PATH . '/views');
define('MODELS_PATH', APP_PATH . '/models');
define('CONFIG_PATH', APP_PATH . '/config');

// Configuration des messages d'erreur
define('ERROR_MESSAGES', [
    'not_logged_in' => 'Vous devez être connecté pour accéder à cette page.',
    'not_authorized' => 'Vous n\'avez pas les droits nécessaires pour accéder à cette page.',
    'invalid_request' => 'Requête invalide.',
    'database_error' => 'Une erreur est survenue lors de l\'accès à la base de données.',
    'validation_error' => 'Les données saisies ne sont pas valides.',
    'not_found' => 'La ressource demandée n\'existe pas.'
]);

// Configuration des statuts
define('STATUS', [
    'EN_ATTENTE' => 'en_attente',
    'VALIDE' => 'validé',
    'REJETE' => 'rejeté'
]);

// Configuration des types de temps
define('TYPES_TEMPS', [
    'HEURE_SUPPLEMENTAIRE' => 'heure_supplementaire',
    'RECUPERATION' => 'recuperation'
]); 