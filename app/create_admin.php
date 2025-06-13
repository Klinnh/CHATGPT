<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Vérifier si la base de données existe
    $db->query("USE gestion_heures_supp");
    echo "Base de données 'gestion_heures_supp' accessible\n";

    // Vérifier si l'utilisateur admin existe déjà
    $stmt = $db->query("SELECT id FROM users WHERE email = 'admin@msi2000.fr'");
    if ($stmt->rowCount() > 0) {
        echo "L'utilisateur admin existe déjà.\n";
        exit;
    }

    // Créer l'utilisateur admin
    $query = "INSERT INTO users (nom, prenom, email, password, role, actif) 
              VALUES ('Admin', 'System', 'admin@msi2000.fr', 
              '$2y$12$o58PiFW0fCuK0UPturh74eTApJQXj724WEQaTa9h5Te8u55HvQ8sS', 
              'admin', 1)";
    
    $db->exec($query);
    echo "Utilisateur admin créé avec succès.\n";
    echo "Email: admin@msi2000.fr\n";
    echo "Mot de passe: password\n";

} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
} 