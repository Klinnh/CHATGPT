<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = new PDO(
        "mysql:host=localhost;port=3307;dbname=gestion_heures_supp",
        "root",
        "",
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4")
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connexion réussie à la base de données\n";
    
    $stmt = $db->query("SELECT * FROM users WHERE role = 'technicien'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Utilisateurs techniciens trouvés : " . count($users) . "\n\n";
    
    foreach ($users as $user) {
        echo "ID: {$user['id']}, Nom: {$user['nom']}, Prénom: {$user['prenom']}, Rôle: {$user['role']}\n";
    }
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
} 