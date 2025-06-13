<?php
require_once 'config/database.php';

try {
    $db = (new Database())->getConnection();
    
    // Vérification des rôles actuels
    $stmt = $db->query("SELECT DISTINCT role FROM users");
    echo "Rôles actuels dans la table users :\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['role'] . "\n";
    }
    
} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
} 