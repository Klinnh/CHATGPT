<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../models/HeureSupplementaire.php';
require_once '../../models/User.php';

// Initialisation de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de la session et du rôle admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    exit('Accès non autorisé');
}

// Récupération des paramètres
$user_id = $_GET['user_id'] ?? null;
$view = $_GET['view'] ?? 'month';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

if (!$user_id) {
    http_response_code(400);
    exit('Paramètres manquants');
}

// Initialisation des modèles
$db = (new Database())->getConnection();
$heureModel = new HeureSupplementaire($db);

// Récupération des heures selon la vue
if ($view === 'year') {
    $heures = $heureModel->getUserHoursByYear($user_id, $year);
    $total = $heureModel->getYearlyTotal($user_id, $year);
    $recups = $heureModel->getYearlyRecups($user_id, $year);
} else {
    $heures = $heureModel->getUserHours($user_id, $month, $year);
    $total = $heureModel->getMonthlyStats($user_id, $year, $month);
    $recups = $heureModel->getMonthlyRecups($user_id, $year, $month);
}

// Vérification des données
if ($heures === false) {
    http_response_code(500);
    exit(json_encode(['error' => 'Erreur lors de la récupération des heures']));
}

// Préparation des données de résumé
$summary = [
    'heures_mois' => number_format($total, 2),
    'recups_mois' => number_format($recups, 2),
    'cumul_annee' => number_format($heureModel->getYearlyTotal($user_id, $year), 2)
];

// Préparation des tableaux HTML
$tableHeures = '<table class="table table-striped">
    <thead>
        <tr>
            <th>Date</th>
            <th>Client</th>
            <th>Début</th>
            <th>Fin</th>
            <th>Durée</th>
            <th>Majoration</th>
            <th>Total</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>';

$tableRecups = '<table class="table table-striped">
    <thead>
        <tr>
            <th>Date</th>
            <th>Début</th>
            <th>Fin</th>
            <th>Durée</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>';

if (!empty($heures)) {
    foreach ($heures as $heure) {
        if ($heure['type'] === 'récupération') {
            $tableRecups .= '<tr>
                <td>' . date('d/m/Y', strtotime($heure['date_jour'])) . '</td>
                <td>' . date('H:i', strtotime($heure['heure_debut'])) . '</td>
                <td>' . date('H:i', strtotime($heure['heure_fin'])) . '</td>
                <td>' . number_format($heure['duree_calculee'], 2) . ' h</td>
                <td><span class="badge bg-' . 
                ($heure['statut'] === 'validé' ? 'success' : ($heure['statut'] === 'rejeté' ? 'danger' : 'warning')) . 
                '">' . ucfirst($heure['statut']) . '</span></td>
            </tr>';
        } else {
            $majoration = $heure['majoration_standard'] + $heure['majoration_superieur'];
            $total_ligne = $heure['duree_calculee'] + $majoration;
            
            $tableHeures .= '<tr>
                <td>' . date('d/m/Y', strtotime($heure['date_jour'])) . '</td>
                <td>' . htmlspecialchars($heure['client_nom']) . '</td>
                <td>' . date('H:i', strtotime($heure['heure_debut'])) . '</td>
                <td>' . date('H:i', strtotime($heure['heure_fin'])) . '</td>
                <td>' . number_format($heure['duree_calculee'], 2) . ' h</td>
                <td>' . number_format($majoration, 2) . ' h</td>
                <td>' . number_format($total_ligne, 2) . ' h</td>
                <td><span class="badge bg-' . 
                ($heure['statut'] === 'validé' ? 'success' : ($heure['statut'] === 'rejeté' ? 'danger' : 'warning')) . 
                '">' . ucfirst($heure['statut']) . '</span></td>
            </tr>';
        }
    }
} else {
    $tableHeures .= '<tr><td colspan="8" class="text-center">Aucune heure supplémentaire pour cette période</td></tr>';
    $tableRecups .= '<tr><td colspan="5" class="text-center">Aucune récupération pour cette période</td></tr>';
}

$tableHeures .= '</tbody></table>';
$tableRecups .= '</tbody></table>';

// Préparation des données pour les graphiques
$evolution_heures = [];
$evolution_recups = [];

for ($i = 1; $i <= 12; $i++) {
    $evolution_heures[] = floatval($heureModel->getMonthlyStats($user_id, $year, $i));
    $evolution_recups[] = floatval($heureModel->getMonthlyRecups($user_id, $year, $i));
}

$charts = [
    'heures' => [
        'labels' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
        'datasets' => [[
            'label' => 'Heures supplémentaires',
            'data' => $evolution_heures,
            'borderColor' => '#005E62',
            'backgroundColor' => 'rgba(0, 94, 98, 0.1)',
            'fill' => true,
            'tension' => 0.4
        ]]
    ],
    'recups' => [
        'labels' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
        'datasets' => [[
            'label' => 'Récupérations',
            'data' => $evolution_recups,
            'borderColor' => '#6AC2D2',
            'backgroundColor' => 'rgba(106, 194, 210, 0.1)',
            'fill' => true,
            'tension' => 0.4
        ]]
    ]
];

// Envoi de la réponse JSON
header('Content-Type: application/json');
echo json_encode([
    'tableHeures' => $tableHeures,
    'tableRecups' => $tableRecups,
    'summary' => $summary,
    'charts' => $charts
]); 