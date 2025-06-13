<?php
require_once 'config/database.php';

try {
    $db = (new Database())->getConnection();
    
    // Création de la table des utilisateurs
    $sql_users = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(50) NOT NULL,
        prenom VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin', 'administratif', 'technicien') NOT NULL DEFAULT 'user',
        matricule VARCHAR(50) UNIQUE,
        date_embauche DATE,
        service VARCHAR(100),
        actif BOOLEAN DEFAULT TRUE,
        derniere_connexion DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql_users);
    
    // Création de la table des clients en premier
    $sql_clients = "CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(191) NOT NULL,
        code VARCHAR(50) UNIQUE,
        adresse TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_nom (nom)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql_clients);
    
    // Modification de la table users pour accepter le rôle 'administratif'
    $sql_update_users = "ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'administratif', 'technicien') NOT NULL DEFAULT 'user'";
    $db->exec($sql_update_users);
    
    // Création de la table des paramètres
    $db->exec("DROP TABLE IF EXISTS parametres_temps");
    $sql_params = "CREATE TABLE parametres_temps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        valeur VARCHAR(50) NOT NULL,
        description TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql_params);
    
    // Insertion des paramètres par défaut
    $sql_params = "INSERT INTO parametres_temps (code, valeur, description) VALUES
        ('heures_jour_standard', '7.00', 'Nombre d''heures standard par jour'),
        ('temps_pause_standard', '1.00', 'Temps de pause standard en heures'),
        ('debut_journee_standard', '08:00', 'Heure de début standard'),
        ('fin_journee_standard', '17:00', 'Heure de fin standard'),
        ('seuil_declenchement_heures_supp', '0.25', 'Seuil minimal pour déclencher des heures supplémentaires'),
        ('heures_semaine_contractuelle', '35.00', 'Nombre d''heures contractuelles par semaine'),
        ('seuil_majoration_heures_supp', '43.00', 'Seuil en heures pour la majoration des heures supplémentaires'),
        ('taux_majoration_standard', '25', 'Taux de majoration standard (en %)'),
        ('taux_majoration_superieur', '50', 'Taux de majoration au-delà du seuil (en %)'),
        ('entite_mere', '1', 'ID de la société principale (MSI2000)')";
    
    $stmt = $db->prepare($sql_params);
    $stmt->execute();
    
    // Suppression et création de la table des heures supplémentaires en dernier
    $db->exec("DROP TABLE IF EXISTS heures_supplementaires");
    $sql_heures = "CREATE TABLE heures_supplementaires (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        client_id INT,
        date_jour DATE NOT NULL,
        heure_debut TIME NOT NULL,
        heure_fin TIME NOT NULL,
        duree_calculee DECIMAL(4,2) NOT NULL,
        type_temps ENUM('heure_supplementaire', 'recuperation') NOT NULL,
        motif ENUM('surcharge', 'urgence', 'remplacement', 'projet', 'autre') NOT NULL,
        temps_pause DECIMAL(4,2) DEFAULT 0,
        statut ENUM('en_attente', 'validé', 'rejeté') NOT NULL DEFAULT 'en_attente',
        commentaire TEXT,
        commentaire_admin TEXT,
        majoration_standard DECIMAL(4,2) DEFAULT 0,
        majoration_superieur DECIMAL(4,2) DEFAULT 0,
        taux_majoration_standard DECIMAL(4,2) DEFAULT 0,
        taux_majoration_superieur DECIMAL(4,2) DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
        INDEX idx_user_date (user_id, date_jour),
        INDEX idx_statut (statut),
        INDEX idx_type (type_temps)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql_heures);
    
    // Mise à jour des rôles des utilisateurs de manière sécurisée
    $sql_update_roles = "UPDATE users SET role = 'administratif' WHERE role = 'manager' OR role NOT IN ('user', 'admin', 'administratif', 'technicien')";
    try {
        $db->exec($sql_update_roles);
    } catch(PDOException $e) {
        echo "Attention : Certains rôles n'ont pas pu être mis à jour. Veuillez vérifier manuellement les rôles des utilisateurs.\n";
    }
    
    echo "Tables créées avec succès!\n";
    
} catch(PDOException $e) {
    echo "Erreur lors de la création des tables : " . $e->getMessage() . "\n";
} 