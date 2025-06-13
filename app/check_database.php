<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Vérifier si la base de données existe
    $db->query("USE gestion_heures_supp");
    echo "Base de données 'gestion_heures_supp' accessible\n";

    // Vérifier les tables
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "\nTables trouvées :\n";
    foreach ($tables as $table) {
        echo "- $table\n";
        
        // Afficher la structure de chaque table
        $columns = $db->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_ASSOC);
        echo "  Structure :\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']}: {$column['Type']}\n";
        }
        echo "\n";
    }

} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
} 