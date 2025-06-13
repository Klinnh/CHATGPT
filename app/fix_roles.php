<?php
require_once 'config/database.php';

try {
    $db = (new Database())->getConnection();
    
    // 1. D'abord, on modifie la structure de la table pour accepter tous les rôles possibles
    $sql_alter = "ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'administratif', 'technicien', 'manager') NOT NULL DEFAULT 'user'";
    $db->exec($sql_alter);
    
    // 2. Ensuite, on met à jour les rôles existants
    $sql_update = "UPDATE users SET role = 'administratif' WHERE role = 'manager'";
    $db->exec($sql_update);
    
    echo "Structure de la table users et rôles mis à jour avec succès!\n";
    
} catch(PDOException $e) {
    echo "Erreur lors de la mise à jour : " . $e->getMessage() . "\n";
} 