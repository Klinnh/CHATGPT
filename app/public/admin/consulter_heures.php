<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/HeureSupplementaire.php';
require_once APP_PATH . '/models/User.php';

session_start();

// Vérification de l'authentification et des droits
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php?error=unauthorized');
    exit;
}

$db = (new Database())->getConnection();
$heureModel = new HeureSupplementaire($db);
$userModel = new User($db);

// Récupération des paramètres de filtrage
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;

// Récupération des utilisateurs
$users = $userModel->readAll();

// Récupération des données pour le graphique
$stats = $heureModel->getMonthlyStats($month, $year);

require_once APP_PATH . '/views/layouts/header.php';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">Consultation des heures supplémentaires</h2>
        </div>
        <div class="card-body">
            <!-- Filtres -->
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label for="month" class="form-label">Mois</label>
                    <select name="month" id="month" class="form-select">
                        <?php
                        for ($i = 1; $i <= 12; $i++) {
                            $selected = $i == $month ? 'selected' : '';
                            echo "<option value='$i' $selected>" . date('F', mktime(0, 0, 0, $i, 1)) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="year" class="form-label">Année</label>
                    <select name="year" id="year" class="form-select">
                        <?php
                        $currentYear = date('Y');
                        for ($i = $currentYear - 1; $i <= $currentYear + 1; $i++) {
                            $selected = $i == $year ? 'selected' : '';
                            echo "<option value='$i' $selected>$i</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="user_id" class="form-label">Employé</label>
                    <select name="user_id" id="user_id" class="form-select">
                        <option value="">Tous les employés</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filtrer</button>
                    <a href="consulter_heures.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>

            <!-- Graphiques -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Répartition des heures par type</h5>
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Évolution des heures par semaine</h5>
                            <canvas id="evolutionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau des heures -->
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Date</th>
                            <th>Heures supp.</th>
                            <th>Majoration 25%</th>
                            <th>Majoration 50%</th>
                            <th>Total avec majoration</th>
                            <th>Type</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $heures = $user_id ? 
                            $heureModel->getUserHours($user_id, $month, $year) :
                            $heureModel->getAllHours($month, $year);
                        
                        foreach ($heures as $heure):
                            $total_majoration = $heure['majoration_standard'] + $heure['majoration_superieur'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($heure['prenom'] . ' ' . $heure['nom']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($heure['date_jour'])); ?></td>
                                <td><?php echo number_format($heure['duree_calculee'], 2); ?> h</td>
                                <td><?php echo number_format($heure['majoration_standard'], 2); ?> h</td>
                                <td><?php echo number_format($heure['majoration_superieur'], 2); ?> h</td>
                                <td><?php echo number_format($total_majoration, 2); ?> h</td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $heure['type_temps'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $heure['statut'] === 'validé' ? 'success' : 
                                            ($heure['statut'] === 'rejeté' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst($heure['statut']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Données pour les graphiques
const typeData = <?php echo json_encode($stats['type']); ?>;
const evolutionData = <?php echo json_encode($stats['evolution']); ?>;

// Graphique de répartition par type
new Chart(document.getElementById('typeChart'), {
    type: 'pie',
    data: {
        labels: ['Heures supplémentaires', 'Récupération'],
        datasets: [{
            data: [typeData.heures_supp, typeData.recuperation],
            backgroundColor: ['#FF6384', '#36A2EB']
        }]
    }
});

// Graphique d'évolution
new Chart(document.getElementById('evolutionChart'), {
    type: 'line',
    data: {
        labels: evolutionData.labels,
        datasets: [{
            label: 'Heures supplémentaires',
            data: evolutionData.heures_supp,
            borderColor: '#FF6384',
            tension: 0.1
        }, {
            label: 'Récupération',
            data: evolutionData.recuperation,
            borderColor: '#36A2EB',
            tension: 0.1
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php require_once APP_PATH . '/views/layouts/footer.php'; ?> 