<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "PHP Self: " . $_SERVER['PHP_SELF'] . "<br>";
echo "Current Directory: " . getcwd() . "<br>";
echo "Base Directory: " . dirname(__DIR__) . "<br>";

// Test de session
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Session Role: " . ($_SESSION['user_role'] ?? 'Non défini') . "<br>";
?> 