<?php
// Démarrage du tampon de sortie
ob_start();

// Définition des constantes
define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH . '/app');

// Démarrage de la session
session_start();

// Vérification des droits d'administrateur
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /heures/login.php?error=unauthorized');
    exit;
}

// Inclusion des fichiers nécessaires
require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/models/CumulSemaine.php';
require_once APP_PATH . '/models/CumulUtilisateur.php';

try {
    // Création des instances
    $db = (new Database())->getConnection();
    $userModel = new User($db);
    $cumulSemaineModel = new CumulSemaine($db);
    $cumulUtilisateurModel = new CumulUtilisateur($db);

    // Récupération de tous les utilisateurs
    $users = $userModel->readAll();
    $users_processed = 0;
    $weeks_updated = 0;
    $errors = [];

    // Pour chaque utilisateur
    foreach ($users as $user) {
        try {
            // Récupération des semaines distinctes avec des heures validées
            $query = "SELECT DISTINCT 
                        YEAR(date_jour) as annee,
                        WEEK(date_jour, 3) as semaine
                     FROM heures_supplementaires 
                     WHERE user_id = ? AND statut = 'validé'
                     ORDER BY annee DESC, semaine DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$user['id']]);
            $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Pour chaque semaine
            foreach ($weeks as $week) {
                // Récupération des heures de la semaine
                $query = "SELECT * 
                         FROM heures_supplementaires 
                         WHERE user_id = ? 
                         AND YEAR(date_jour) = ? 
                         AND WEEK(date_jour, 3) = ?
                         AND statut = 'validé'
                         ORDER BY date_jour ASC";
                
                $stmt = $db->prepare($query);
                $stmt->execute([$user['id'], $week['annee'], $week['semaine']]);
                $heures = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Vérifier si la semaine doit être recalculée
                if ($cumulSemaineModel->semaineARecalculer($user['id'], $week['annee'], $week['semaine'], $heures)) {
                    // Calculer le cumul de la semaine
                    if ($cumulSemaineModel->calculerCumulSemaine($user['id'], $week['annee'], $week['semaine'])) {
                        $weeks_updated++;
                    }
                }
            }
            
            // Mettre à jour le cumul global de l'utilisateur
            $cumulUtilisateurModel->calculerCumul($user['id']);
            $users_processed++;
            
        } catch (Exception $e) {
            $errors[] = "Erreur pour l'utilisateur {$user['id']}: " . $e->getMessage();
        }
    }
    
    // Redirection avec message de succès
    $_SESSION['success'] = "Recalcul effectué avec succès !\n";
    $_SESSION['success'] .= "- {$users_processed} utilisateurs traités\n";
    $_SESSION['success'] .= "- {$weeks_updated} semaines mises à jour\n";
    
    if (!empty($errors)) {
        $_SESSION['success'] .= "\nErreurs rencontrées :\n" . implode("\n", $errors);
    }

    header('Location: /heures/admin/index.php');
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur lors du recalcul : " . $e->getMessage();
    header('Location: /heures/admin/index.php');
    exit;
} 