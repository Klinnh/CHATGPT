<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../models/HeureSupplementaire.php';
require_once '../../models/User.php';

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
$userModel = new User($db);

// Récupération des informations de l'utilisateur
$user = $userModel->getById($user_id);

if (!$user) {
    http_response_code(404);
    exit('Utilisateur non trouvé');
}

// Récupération des heures selon la vue
if ($view === 'year') {
    $heures = $heureModel->getUserHoursByYear($user_id, $year);
    $total = $heureModel->getYearlyTotal($user_id, $year);
    $periode = $year;
} else {
    $heures = $heureModel->getUserHours($user_id, $month, $year);
    $total = $heureModel->getMonthlyStats($user_id, $year, $month);
    $mois_fr = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
    ];
    $periode = $mois_fr[(int)$month] . ' ' . $year;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .table {
                width: 100% !important;
                margin-bottom: 1rem;
                break-inside: auto;
                font-size: 11px;
            }
            .table th {
                background-color: #f8f9fa !important;
                -webkit-print-color-adjust: exact;
            }
            .badge {
                border: 1px solid #000;
                font-size: 10px;
            }
            .charts-container {
                break-inside: avoid;
                margin-bottom: 20px;
            }
            .card {
                break-inside: avoid;
                margin-bottom: 1rem;
            }
            .card-title {
                font-size: 14px;
                margin-bottom: 0.5rem;
            }
            .card-text {
                font-size: 13px;
            }
            .header h1 {
                font-size: 20px;
                margin-bottom: 0.5rem;
            }
            .header .lead {
                font-size: 16px;
                margin-bottom: 1rem;
            }
            @page {
                size: portrait;
                margin: 1cm;
            }
        }
        .header {
            margin-bottom: 1.5rem;
        }
        .summary {
            margin-bottom: 1.5rem;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .charts-container {
            margin-bottom: 1.5rem;
        }
        .card {
            box-shadow: none;
            border: 1px solid rgba(0,0,0,.125);
        }
        .card-header {
            background-color: #f8f9fa;
            padding: 0.5rem 1rem;
        }
        .table td, .table th {
            padding: 0.5rem;
        }
    </style>
</head>
<body class="container-fluid mt-3">
    <div class="header">
        <h1>Statistiques des heures supplémentaires</h1>
        <p class="lead">
            <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?> - <?php echo $periode; ?>
        </p>
    </div>

    <div class="summary row g-2">
        <div class="col-4">
            <div class="card h-100">
                <div class="card-body p-2">
                    <h5 class="card-title">Total des heures</h5>
                    <p class="card-text"><?php echo number_format($total, 2); ?> h</p>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card h-100">
                <div class="card-body p-2">
                    <h5 class="card-title">Récupérations</h5>
                    <p class="card-text">
                        <?php echo number_format($heureModel->getMonthlyRecups($user_id, $year, $month), 2); ?> h
                    </p>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card h-100">
                <div class="card-body p-2">
                    <h5 class="card-title">Cumul annuel</h5>
                    <p class="card-text">
                        <?php echo number_format($heureModel->getYearlyTotal($user_id, $year), 2); ?> h
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques -->
    <div class="charts-container row g-2">
        <div class="col-6">
            <div class="card">
                <div class="card-body p-2">
                    <h6 class="card-title">Évolution des heures par mois</h6>
                    <canvas id="heuresEvolutionChart" style="height: 150px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card">
                <div class="card-body p-2">
                    <h6 class="card-title">Évolution des récupérations par mois</h6>
                    <canvas id="recupsEvolutionChart" style="height: 150px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau des heures supplémentaires -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Heures supplémentaires</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
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
                    <tbody>
                        <?php foreach ($heures as $heure): 
                            if ($heure['type'] !== 'récupération'):
                                $majoration = $heure['majoration_standard'] + $heure['majoration_superieur'];
                                $total_ligne = $heure['duree_calculee'] + $majoration;
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($heure['date_jour'])); ?></td>
                            <td><?php echo htmlspecialchars($heure['client_nom']); ?></td>
                            <td><?php echo date('H:i', strtotime($heure['heure_debut'])); ?></td>
                            <td><?php echo date('H:i', strtotime($heure['heure_fin'])); ?></td>
                            <td><?php echo number_format($heure['duree_calculee'], 2); ?> h</td>
                            <td><?php echo number_format($majoration, 2); ?> h</td>
                            <td><?php echo number_format($total_ligne, 2); ?> h</td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $heure['statut'] === 'validé' ? 'success' : 
                                        ($heure['statut'] === 'rejeté' ? 'danger' : 'warning'); 
                                ?>">
                                    <?php echo ucfirst($heure['statut']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tableau des récupérations -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Récupérations</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Durée</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($heures as $heure): 
                            if ($heure['type'] === 'récupération'):
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($heure['date_jour'])); ?></td>
                            <td><?php echo date('H:i', strtotime($heure['heure_debut'])); ?></td>
                            <td><?php echo date('H:i', strtotime($heure['heure_fin'])); ?></td>
                            <td><?php echo number_format($heure['duree_calculee'], 2); ?> h</td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $heure['statut'] === 'validé' ? 'success' : 
                                        ($heure['statut'] === 'rejeté' ? 'danger' : 'warning'); 
                                ?>">
                                    <?php echo ucfirst($heure['statut']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="no-print text-center mt-4">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            Fermer
        </button>
    </div>

    <script>
        // Données pour les graphiques
        const evolutionData = <?php 
            $evolution_heures = [];
            $evolution_recups = [];
            for ($i = 1; $i <= 12; $i++) {
                $evolution_heures[] = floatval($heureModel->getMonthlyStats($user_id, $year, $i));
                $evolution_recups[] = floatval($heureModel->getMonthlyRecups($user_id, $year, $i));
            }
            echo json_encode([
                'heures' => $evolution_heures,
                'recups' => $evolution_recups,
                'labels' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc']
            ]);
        ?>;

        // Configuration des graphiques
        const chartConfig = {
            type: 'line',
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        };

        // Création des graphiques
        new Chart(
            document.getElementById('heuresEvolutionChart'),
            {
                ...chartConfig,
                data: {
                    labels: evolutionData.labels,
                    datasets: [{
                        label: 'Heures supplémentaires',
                        data: evolutionData.heures,
                        borderColor: '#005E62',
                        backgroundColor: 'rgba(0, 94, 98, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                }
            }
        );

        new Chart(
            document.getElementById('recupsEvolutionChart'),
            {
                ...chartConfig,
                data: {
                    labels: evolutionData.labels,
                    datasets: [{
                        label: 'Récupérations',
                        data: evolutionData.recups,
                        borderColor: '#6AC2D2',
                        backgroundColor: 'rgba(106, 194, 210, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                }
            }
        );

        // Imprimer automatiquement
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html> 