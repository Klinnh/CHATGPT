<?php
define('ROOT_PATH', dirname(dirname(__DIR__)));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    
    // Lecture du fichier SQL
    $sql = file_get_contents(APP_PATH . '/sql/create_cumuls_utilisateur.sql');
    
    // Exécution du script SQL
    $result = $db->exec($sql);
    
    echo "La table cumuls_utilisateur a été créée et initialisée avec succès.\n";
    
} catch (PDOException $e) {
    echo "Erreur lors de la création de la table : " . $e->getMessage() . "\n";
} 