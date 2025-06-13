<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../models/User.php';
require_once '../../models/HeureSupplementaire.php';

// Traduction des mois en français
$mois_fr = [
    1 => 'Janvier',
    2 => 'Février',
    3 => 'Mars',
    4 => 'Avril',
    5 => 'Mai',
    6 => 'Juin',
    7 => 'Juillet',
    8 => 'Août',
    9 => 'Septembre',
    10 => 'Octobre',
    11 => 'Novembre',
    12 => 'Décembre'
];

// Vérification de la session et des permissions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../helpers/auth_helper.php';
checkPageAccess('statistiques');

// Récupération des paramètres de filtrage
$annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
$mois = isset($_GET['mois']) ? (int)$_GET['mois'] : (int)date('m');

// Connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Récupération de tous les utilisateurs
$user = new User($db);
$users_stmt = $user->read();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des statistiques pour chaque utilisateur
$heureModel = new HeureSupplementaire($db);
$stats = [];
foreach ($users as $user) {
    // Données d'évolution pour l'année
    $evolution_heures = [];
    $evolution_recups = [];
    for ($i = 1; $i <= 12; $i++) {
        $evolution_heures[] = floatval($heureModel->getMonthlyStats($user['id'], $annee, $i));
        $evolution_recups[] = floatval($heureModel->getMonthlyRecups($user['id'], $annee, $i));
    }

    $stats[$user['id']] = [
        'user' => $user,
        'heures_mois' => $heureModel->getMonthlyStats($user['id'], $annee, $mois),
        'recups_mois' => $heureModel->getMonthlyRecups($user['id'], $annee, $mois),
        'cumul_annee' => $heureModel->getYearlyTotal($user['id'], $annee),
        'evolution_heures' => $evolution_heures,
        'evolution_recups' => $evolution_recups
    ];
}

// Calcul des totaux globaux
$totaux = [
    'heures_mois' => 0,
    'recups_mois' => 0,
    'cumul_annee' => 0
];

foreach ($stats as $stat) {
    $totaux['heures_mois'] += $stat['heures_mois'];
    $totaux['recups_mois'] += $stat['recups_mois'];
    $totaux['cumul_annee'] += $stat['cumul_annee'];
}

// Inclusion du header
require_once '../../views/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2>Statistiques des heures supplémentaires</h2>
        <p class="text-muted">Période : <?php echo $mois_fr[$mois] . ' ' . $annee; ?></p>
    </div>
    <div class="col-auto">
        <form method="GET" class="row g-3">
            <div class="col-auto">
                <select name="mois" class="form-select">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $i === $mois ? 'selected' : ''; ?>>
                            <?php echo $mois_fr[$i]; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="annee" class="form-select">
                    <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $i === $annee ? 'selected' : ''; ?>>
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Cartes de résumé -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Total heures du mois</h5>
                <h2 class="card-text text-primary"><?php echo number_format($totaux['heures_mois'], 2); ?> h</h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Total récupérations du mois</h5>
                <h2 class="card-text text-primary"><?php echo number_format($totaux['recups_mois'], 2); ?> h</h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Cumul annuel</h5>
                <h2 class="card-text text-primary"><?php echo number_format($totaux['cumul_annee'], 2); ?> h</h2>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Répartition des heures par employé</h5>
                <canvas id="heuresChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Répartition des récupérations par employé</h5>
                <canvas id="recupsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tableau détaillé -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Détail par employé</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Employé</th>
                        <th>Heures du mois</th>
                        <th>Récupérations du mois</th>
                        <th>Cumul annuel</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $stat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stat['user']['nom'] . ' ' . $stat['user']['prenom']); ?></td>
                            <td><?php echo number_format($stat['heures_mois'], 2); ?> h</td>
                            <td><?php echo number_format($stat['recups_mois'], 2); ?> h</td>
                            <td><?php echo number_format($stat['cumul_annee'], 2); ?> h</td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#userStatsModal<?php echo $stat['user']['id']; ?>">
                                    <i class="fas fa-eye"></i> Détail
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals pour les statistiques détaillées -->
<?php foreach ($stats as $stat): ?>
<div class="modal fade" id="userStatsModal<?php echo $stat['user']['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Statistiques de <?php echo htmlspecialchars($stat['user']['nom'] . ' ' . $stat['user']['prenom']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Filtres -->
                <div class="filter-section mb-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Vue</label>
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-theme active" data-view="month" onclick="changeView(this, <?php echo $stat['user']['id']; ?>)">
                                    Mensuelle
                                </button>
                                <button type="button" class="btn btn-outline-theme" data-view="year" onclick="changeView(this, <?php echo $stat['user']['id']; ?>)">
                                    Annuelle
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4 month-filter">
                            <label class="form-label">Mois</label>
                            <select name="mois" class="form-select" onchange="updateUserStats(<?php echo $stat['user']['id']; ?>)">
                                <?php foreach ($mois_fr as $num => $nom): ?>
                                    <option value="<?php echo $num; ?>" <?php echo $num == $mois ? 'selected' : ''; ?>>
                                        <?php echo $nom; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Année</label>
                            <select name="annee" class="form-select" onchange="updateUserStats(<?php echo $stat['user']['id']; ?>)">
                                <?php for ($i = date('Y'); $i >= date('Y') - 2; $i--): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == $annee ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Graphiques en haut -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Évolution des heures par mois</h6>
                                <canvas id="heuresEvolutionChart<?php echo $stat['user']['id']; ?>"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Évolution des récupérations par mois</h6>
                                <canvas id="recupsEvolutionChart<?php echo $stat['user']['id']; ?>"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cartes de résumé -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Heures du mois</h6>
                                <h3 class="card-text text-primary"><?php echo number_format($stat['heures_mois'], 2); ?> h</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Récupérations du mois</h6>
                                <h3 class="card-text text-primary"><?php echo number_format($stat['recups_mois'], 2); ?> h</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Cumul annuel</h6>
                                <h3 class="card-text text-primary"><?php echo number_format($stat['cumul_annee'], 2); ?> h</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableaux des heures -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Heures supplémentaires</h6>
                        <button class="btn btn-outline-primary btn-sm" onclick="printUserStats(<?php echo $stat['user']['id']; ?>)">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" id="userStatsTableHeures<?php echo $stat['user']['id']; ?>">
                            <!-- Le tableau des heures supplémentaires sera chargé dynamiquement via AJAX -->
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Récupérations</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" id="userStatsTableRecups<?php echo $stat['user']['id']; ?>">
                            <!-- Le tableau des récupérations sera chargé dynamiquement via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Scripts pour les graphiques -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Préparation des données pour les graphiques
    const stats = <?php echo json_encode(array_values($stats)); ?>;
    const moisLabels = <?php echo json_encode(array_values($mois_fr)); ?>;
    
    // Données pour le graphique des heures
    const heuresData = {
        labels: stats.map(stat => `${stat.user.nom} ${stat.user.prenom}`),
        datasets: [{
            label: 'Heures supplémentaires',
            data: stats.map(stat => stat.heures_mois),
            backgroundColor: '#005E62',
            borderColor: '#005E62',
            borderWidth: 1
        }]
    };

    // Données pour le graphique des récupérations
    const recupsData = {
        labels: stats.map(stat => `${stat.user.nom} ${stat.user.prenom}`),
        datasets: [{
            label: 'Récupérations',
            data: stats.map(stat => stat.recups_mois),
            backgroundColor: '#6AC2D2',
            borderColor: '#6AC2D2',
            borderWidth: 1
        }]
    };

    // Configuration commune des graphiques
    const config = {
        type: 'bar',
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Heures'
                    }
                }
            }
        }
    };

    // Création des graphiques
    new Chart(
        document.getElementById('heuresChart'),
        { ...config, data: heuresData }
    );

    new Chart(
        document.getElementById('recupsChart'),
        { ...config, data: recupsData }
    );

    // Création des graphiques d'évolution dans les modals
    stats.forEach(stat => {
        const userId = stat.user.id;

        // Configuration des graphiques d'évolution
        const evolutionConfig = {
            type: 'line',
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Heures'
                        }
                    }
                }
            }
        };

        // Création des graphiques d'évolution
        if (document.getElementById(`heuresEvolutionChart${userId}`)) {
            new Chart(
                document.getElementById(`heuresEvolutionChart${userId}`),
                {
                    ...evolutionConfig,
                    data: {
                        labels: moisLabels,
                        datasets: [{
                            label: 'Heures supplémentaires',
                            data: stat.evolution_heures,
                            borderColor: '#005E62',
                            backgroundColor: 'rgba(0, 94, 98, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    }
                }
            );
        }

        if (document.getElementById(`recupsEvolutionChart${userId}`)) {
            new Chart(
                document.getElementById(`recupsEvolutionChart${userId}`),
                {
                    ...evolutionConfig,
                    data: {
                        labels: moisLabels,
                        datasets: [{
                            label: 'Récupérations',
                            data: stat.evolution_recups,
                            borderColor: '#6AC2D2',
                            backgroundColor: 'rgba(106, 194, 210, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    }
                }
            );
        }
    });
});

function changeView(button, userId) {
    // Mettre à jour l'état actif des boutons
    button.parentElement.querySelectorAll('.btn').forEach(btn => btn.classList.remove('active'));
    button.classList.add('active');

    // Afficher/masquer le sélecteur de mois selon la vue
    const monthFilter = button.closest('.filter-section').querySelector('.month-filter');
    monthFilter.style.display = button.dataset.view === 'month' ? 'block' : 'none';

    // Mettre à jour les statistiques
    updateUserStats(userId);
}

function updateUserStats(userId) {
    const modal = document.getElementById(`userStatsModal${userId}`);
    const view = modal.querySelector('.btn-group .active').dataset.view;
    const yearSelect = modal.querySelector('select[name="annee"]');
    const monthSelect = modal.querySelector('select[name="mois"]');
    const year = yearSelect.value;
    const month = monthSelect.value;

    // Appel AJAX pour récupérer les données
    fetch(`get_user_stats.php?user_id=${userId}&view=${view}&year=${year}&month=${month}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau');
            }
            return response.json();
        })
        .then(data => {
            // Mettre à jour les tableaux
            document.getElementById(`userStatsTableHeures${userId}`).innerHTML = data.tableHeures;
            document.getElementById(`userStatsTableRecups${userId}`).innerHTML = data.tableRecups;
            
            // Mettre à jour les cartes de résumé
            const cards = modal.querySelectorAll('.card-text');
            cards[0].textContent = `${data.summary.heures_mois} h`;
            cards[1].textContent = `${data.summary.recups_mois} h`;
            cards[2].textContent = `${data.summary.cumul_annee} h`;
            
            // Mettre à jour les graphiques
            if (data.charts) {
                const heuresChart = Chart.getChart(`heuresEvolutionChart${userId}`);
                const recupsChart = Chart.getChart(`recupsEvolutionChart${userId}`);

                if (heuresChart) {
                    heuresChart.data.datasets[0].data = data.charts.heures.datasets[0].data;
                    heuresChart.update();
                }
                if (recupsChart) {
                    recupsChart.data.datasets[0].data = data.charts.recups.datasets[0].data;
                    recupsChart.update();
                }
            }
        })
        .catch(error => {
            console.error('Erreur lors de la mise à jour des statistiques:', error);
            alert('Une erreur est survenue lors de la mise à jour des statistiques');
        });
}

function printUserStats(userId) {
    const modal = document.getElementById(`userStatsModal${userId}`);
    const view = modal.querySelector('.btn-group .active').dataset.view;
    const yearSelect = modal.querySelector('select[name="annee"]');
    const monthSelect = modal.querySelector('select[name="mois"]');
    const year = yearSelect.value;
    const month = monthSelect.value;

    // Ouvrir une nouvelle fenêtre pour l'impression
    window.open(
        `print_user_stats.php?user_id=${userId}&view=${view}&year=${year}&month=${month}`,
        '_blank'
    );
}
</script>

<?php require_once '../../views/layouts/footer.php'; ?> 