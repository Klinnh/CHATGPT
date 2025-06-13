<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/HeureSupplementaire.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /heures/login.php?error=not_logged_in');
    exit;
}

$db = (new Database())->getConnection();

// Paramètres de filtrage
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_conditions = ['h.user_id = ?'];
$params = [$_SESSION['user_id']];

// Filtre par type
if (isset($_GET['type']) && in_array($_GET['type'], ['heure_supplementaire', 'recuperation'])) {
    $where_conditions[] = 'h.type_temps = ?';
    $params[] = $_GET['type'];
}

// Filtre par statut
if (isset($_GET['statut']) && in_array($_GET['statut'], ['en_attente', 'validé', 'refusé'])) {
    $where_conditions[] = 'h.statut = ?';
    $params[] = $_GET['statut'];
}

// Filtre par date
if (isset($_GET['date_debut']) && $_GET['date_debut']) {
    $where_conditions[] = 'h.date_jour >= ?';
    $params[] = $_GET['date_debut'];
}
if (isset($_GET['date_fin']) && $_GET['date_fin']) {
    $where_conditions[] = 'h.date_jour <= ?';
    $params[] = $_GET['date_fin'];
}

// Construction de la requête
$where_clause = implode(' AND ', $where_conditions);

// Compte total pour la pagination
$count_query = "SELECT COUNT(*) as total FROM heures_supplementaires h WHERE " . $where_clause;
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Récupération des déclarations
$query = "SELECT h.*, c.nom as client_nom 
          FROM heures_supplementaires h
          LEFT JOIN clients c ON h.client_id = c.id
          WHERE " . $where_clause . "
          ORDER BY h.date_jour DESC, h.heure_debut DESC
          LIMIT " . (int)$offset . ", " . (int)$limit;

$stmt = $db->prepare($query);
$stmt->execute($params);
$declarations = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once APP_PATH . '/views/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Historique des déclarations</h1>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="type" class="form-label">Type</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Tous</option>
                        <option value="heure_supplementaire" <?php echo isset($_GET['type']) && $_GET['type'] === 'heure_supplementaire' ? 'selected' : ''; ?>>Heures supplémentaires</option>
                        <option value="recuperation" <?php echo isset($_GET['type']) && $_GET['type'] === 'recuperation' ? 'selected' : ''; ?>>Récupérations</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="statut" class="form-label">Statut</label>
                    <select class="form-select" id="statut" name="statut">
                        <option value="">Tous</option>
                        <option value="en_attente" <?php echo isset($_GET['statut']) && $_GET['statut'] === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                        <option value="validé" <?php echo isset($_GET['statut']) && $_GET['statut'] === 'validé' ? 'selected' : ''; ?>>Validé</option>
                        <option value="refusé" <?php echo isset($_GET['statut']) && $_GET['statut'] === 'refusé' ? 'selected' : ''; ?>>Refusé</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_debut" class="form-label">Date début</label>
                    <input type="date" class="form-control" id="date_debut" name="date_debut" 
                           value="<?php echo $_GET['date_debut'] ?? ''; ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_fin" class="form-label">Date fin</label>
                    <input type="date" class="form-control" id="date_fin" name="date_fin"
                           value="<?php echo $_GET['date_fin'] ?? ''; ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="historique.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des déclarations -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Durée</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($declarations as $declaration): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($declaration['date_jour'])); ?></td>
                            <td><?php echo htmlspecialchars($declaration['client_nom'] ?? '-'); ?></td>
                            <td>
                                <?php if ($declaration['type_temps'] === 'heure_supplementaire'): ?>
                                    <span class="badge bg-primary">Heure supp</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Récup</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $declaration['heure_debut']; ?></td>
                            <td><?php echo $declaration['heure_fin']; ?></td>
                            <td><?php echo number_format($declaration['duree_calculee'], 1); ?> h</td>
                            <td>
                                <?php if ($declaration['statut'] === 'en_attente'): ?>
                                    <span class="status-badge en-attente">En attente</span>
                                <?php elseif ($declaration['statut'] === 'validé'): ?>
                                    <span class="status-badge valide">Validé</span>
                                <?php elseif ($declaration['statut'] === 'refusé'): ?>
                                    <span class="status-badge refuse">Refusé</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($declaration['statut'] === 'en_attente'): ?>
                                    <a href="/heures/modifier.php?id=<?php echo $declaration['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmerSuppression(<?php echo $declaration['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                echo isset($_GET['type']) ? '&type=' . htmlspecialchars($_GET['type']) : ''; 
                                echo isset($_GET['statut']) ? '&statut=' . htmlspecialchars($_GET['statut']) : '';
                                echo isset($_GET['date_debut']) ? '&date_debut=' . htmlspecialchars($_GET['date_debut']) : '';
                                echo isset($_GET['date_fin']) ? '&date_fin=' . htmlspecialchars($_GET['date_fin']) : '';
                            ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmerSuppression(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette déclaration ?')) {
        window.location.href = '/heures/supprimer.php?id=' + id;
    }
}
</script>

<style>
.status-badge {
    padding: 0.25em 0.6em;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 500;
}

.status-badge.en-attente {
    background-color: #ffc107;
    color: #000;
}

.status-badge.valide {
    background-color: #198754;
    color: #fff;
}

.status-badge.refuse {
    background-color: #dc3545;
    color: #fff;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.2rem;
}

.pagination {
    margin-bottom: 0;
}

.page-link {
    color: var(--msi-primary);
}

.page-item.active .page-link {
    background-color: var(--msi-primary);
    border-color: var(--msi-primary);
}
</style> 