<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Vérifier si la base de données existe
    $db->query("USE gestion_heures_supp");
    echo "Base de données 'gestion_heures_supp' accessible\n";

    // Vérifier la table users
    $users = $db->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nUtilisateurs dans la base de données :\n";
    foreach ($users as $user) {
        echo "ID: {$user['id']}\n";
        echo "Nom: {$user['nom']}\n";
        echo "Prénom: {$user['prenom']}\n";
        echo "Email: {$user['email']}\n";
        echo "Rôle: {$user['role']}\n";
        echo "-------------------\n";
    }

} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
} 