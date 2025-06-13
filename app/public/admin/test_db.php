<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = new PDO(
        "mysql:host=localhost;port=3307;dbname=gestion_heures_supp",
        "root",
        ""
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connexion réussie à la base de données<br>";
    
    // Test de création de table
    $sql = "CREATE TABLE IF NOT EXISTS test_table (id INT PRIMARY KEY)";
    $db->exec($sql);
    echo "Table de test créée avec succès<br>";
    
    // Vérification des tables existantes
    $stmt = $db->query("SHOW TABLES");
    echo "Tables dans la base de données :<br>";
    while ($row = $stmt->fetch()) {
        echo "- " . $row[0] . "<br>";
    }
    
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "<br>";
    echo "Code d'erreur : " . $e->getCode();
} 