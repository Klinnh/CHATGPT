<?php
require_once 'config/database.php';

try {
    $db = (new Database())->getConnection();
    
    // Mise à jour simple des rôles de manager vers administratif
    $sql = "UPDATE users SET role = 'administratif' WHERE role = 'manager'";
    $db->exec($sql);
    
    echo "Rôles mis à jour avec succès!\n";
    
} catch(PDOException $e) {
    echo "Erreur lors de la mise à jour des rôles : " . $e->getMessage() . "\n";
} 