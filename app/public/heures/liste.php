<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inclusion de la configuration de base
require_once '../../config/config.php';

// Inclusion des autres fichiers avec APP_PATH maintenant défini
require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/HeureSupplementaire.php';

// Vérification de la connexion
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Récupération des paramètres de filtrage
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'month'; // 'month' ou 'year'
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m'); // Conversion en entier pour enlever le zéro devant
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Initialisation du modèle
$heureSupplementaire = new HeureSupplementaire($conn);

// Récupération des clients
$stmt = $conn->query("SELECT id, nom, code FROM clients ORDER BY nom");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des heures selon le type de filtre
if ($filter_type === 'year') {
    $heures = $heureSupplementaire->getUserHoursByYear($_SESSION['user_id'], $year);
    $totalMensuel = $heureSupplementaire->calculateYearlyTotal($_SESSION['user_id'], $year);
} else {
    $heures = $heureSupplementaire->getUserHours($_SESSION['user_id'], $month, $year);
    $totalMensuel = $heureSupplementaire->calculateMonthlyTotal($_SESSION['user_id'], $month, $year);
}

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

require_once '../../views/layouts/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Mes Heures Supplémentaires</h1>
        <a href="ajouter.php" class="btn btn-new">
            <i class="bi bi-plus-circle"></i> Nouvelle déclaration
        </a>
    </div>

    <!-- Section des filtres -->
    <div class="filter-section">
        <div class="filter-type-switch">
            <div class="btn-group" role="group">
                <a href="?filter_type=month&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                   class="btn btn-outline-theme <?php echo $filter_type === 'month' ? 'active' : ''; ?>">
                    Vue mensuelle
                </a>
                <a href="?filter_type=year&year=<?php echo $year; ?>" 
                   class="btn btn-outline-theme <?php echo $filter_type === 'year' ? 'active' : ''; ?>">
                    Vue annuelle
                </a>
            </div>
        </div>

        <div class="row">
            <?php if ($filter_type === 'month'): ?>
            <div class="col-md-6">
                <label for="month" class="form-label">Mois</label>
                <select class="form-select" id="month" onchange="updateFilters()">
                    <?php foreach ($mois_fr as $num => $nom): ?>
                        <option value="<?php echo $num; ?>" <?php echo $num == $month ? 'selected' : ''; ?>>
                            <?php echo $nom; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-6">
                <label for="year" class="form-label">Année</label>
                <select class="form-select" id="year" onchange="updateFilters()">
                    <?php for ($i = date('Y'); $i >= date('Y') - 2; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php echo $i == $year ? 'selected' : ''; ?>>
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Résumé -->
    <div class="resume-box">
        <p>
            <?php if ($filter_type === 'year'): ?>
                Total des heures supplémentaires pour <?php echo $year; ?> : 
                <strong><?php echo number_format($totalMensuel, 2); ?> heures</strong>
            <?php else: ?>
                Total des heures supplémentaires pour <?php echo $mois_fr[$month]; ?> <?php echo $year; ?> : 
                <strong><?php echo number_format($totalMensuel, 2); ?> heures</strong>
            <?php endif; ?>
        </p>
    </div>

    <!-- Tableau des heures -->
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Durée</th>
                    <th>Type</th>
                    <th>Commentaire</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($heures as $heure): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($heure['date_jour'])); ?></td>
                    <td><?php echo htmlspecialchars($heure['client_nom']); ?></td>
                    <td><?php echo $heure['heure_debut']; ?></td>
                    <td><?php echo $heure['heure_fin']; ?></td>
                    <td><?php echo number_format($heure['duree_calculee'], 2); ?> h</td>
                    <td>
                        <?php if ($heure['type_temps'] === 'heure_supplementaire'): ?>
                            <span class="badge" style="background-color: #0B5345;">Heure supplémentaire</span>
                        <?php else: ?>
                            <span class="badge" style="background-color: #9CDB9A;">Récupération</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($heure['commentaire'] ?? ''); ?></td>
                    <td>
                        <?php if ($heure['statut'] === 'validé'): ?>
                            <span class="badge bg-success">Validé</span>
                        <?php elseif ($heure['statut'] === 'rejeté'): ?>
                            <span class="badge bg-danger">Rejeté</span>
                        <?php else: ?>
                            <span class="badge bg-warning">En attente</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($heure['statut'] === 'en_attente'): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editModal<?php echo $heure['id']; ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>

                <!-- Modal d'édition -->
                <?php if ($heure['statut'] === 'en_attente'): ?>
                <div class="modal fade" id="editModal<?php echo $heure['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Modifier la déclaration</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form action="modifier.php" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="id" value="<?php echo $heure['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="date_<?php echo $heure['id']; ?>" class="form-label">Date</label>
                                        <input type="date" class="form-control" id="date_<?php echo $heure['id']; ?>" 
                                               name="date" value="<?php echo $heure['date_jour']; ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="client_id_<?php echo $heure['id']; ?>" class="form-label">Client *</label>
                                        <select class="form-select" id="client_id_<?php echo $heure['id']; ?>" name="client_id" required>
                                            <option value="">Sélectionnez un client</option>
                                            <?php foreach ($clients as $client): ?>
                                                <option value="<?php echo $client['id']; ?>" 
                                                        <?php echo $client['id'] == $heure['client_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($client['nom']); ?>
                                                    <?php if ($client['code']): ?>
                                                        (<?php echo htmlspecialchars($client['code']); ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="heure_debut_<?php echo $heure['id']; ?>" class="form-label">Heure de début</label>
                                            <input type="time" class="form-control" id="heure_debut_<?php echo $heure['id']; ?>" 
                                                   name="heure_debut" value="<?php echo $heure['heure_debut']; ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="heure_fin_<?php echo $heure['id']; ?>" class="form-label">Heure de fin</label>
                                            <input type="time" class="form-control" id="heure_fin_<?php echo $heure['id']; ?>" 
                                                   name="heure_fin" value="<?php echo $heure['heure_fin']; ?>" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="commentaire_<?php echo $heure['id']; ?>" class="form-label">Commentaire</label>
                                        <textarea class="form-control" id="commentaire_<?php echo $heure['id']; ?>" 
                                                  name="commentaire" rows="3"><?php echo htmlspecialchars($heure['commentaire'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function updateFilters() {
        const filterType = '<?php echo $filter_type; ?>';
        const month = document.getElementById('month').value;
        const year = document.getElementById('year').value;
        
        let url = '?filter_type=' + filterType + '&year=' + year;
        if (filterType === 'month') {
            url += '&month=' + month;
        }
        
        window.location.href = url;
    }
</script>

<?php include VIEWS_PATH . '/layouts/footer.php'; ?> 