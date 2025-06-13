<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../models/User.php';
require_once '../../models/CumulSemaine.php';
require_once '../../models/CumulUtilisateur.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    $db = (new Database())->getConnection();
    $userModel = new User($db);
    $cumulSemaineModel = new CumulSemaine($db);
    $cumulUtilisateurModel = new CumulUtilisateur($db);

    $users = $userModel->readAll();
    $users_processed = 0;
    $weeks_updated = 0;
    $errors = [];

    foreach ($users as $user) {
        try {
            $user_id = $user['id'];

            // Récupération des semaines distinctes avec des heures validées
            $stmt = $db->prepare("
                SELECT DISTINCT 
                    YEAR(date_jour) as annee,
                    WEEK(date_jour, 3) as semaine
                FROM heures_supplementaires 
                WHERE user_id = ? AND statut = 'validé'
                ORDER BY annee DESC, semaine DESC");
            $stmt->execute([$user_id]);
            $semaines = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($semaines as $periode) {
                $cumulSemaineModel->calculerCumulSemaine($user_id, $periode['annee'], $periode['semaine']);
                $weeks_updated++;
            }

            // Recalcul du cumul utilisateur global
            $cumulUtilisateurModel->calculerCumul($user_id);
            $users_processed++;
        } catch (Exception $e) {
            $errors[] = "Erreur pour l'utilisateur $user_id : " . $e->getMessage();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Recalcul terminé : $users_processed utilisateurs, $weeks_updated semaines.",
        'errors' => $errors
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur fatale : ' . $e->getMessage()
    ]);
}
