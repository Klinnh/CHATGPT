<?php
// Affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définition des constantes
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH);

// Démarrage de la session
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php?error=not_logged_in');
    exit;
}

// Inclusion des fichiers nécessaires
require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/models/Client.php';
require_once APP_PATH . '/models/HeureSupplementaire.php';
require_once APP_PATH . '/models/CumulUtilisateur.php';
require_once APP_PATH . '/models/CumulSemaine.php';
require_once APP_PATH . '/helpers/auth_helper.php';

// Création de l'instance de base de données et des modèles
$db = (new Database())->getConnection();
$userModel = new User($db);
$clientModel = new Client($db);
$heureModel = new HeureSupplementaire($db);
$cumulModel = new CumulUtilisateur($db);
$cumulSemaine = new CumulSemaine($db);

// Récupération des données de l'utilisateur
$user = $userModel->readOne($_SESSION['user_id']);

// Vérification si l'utilisateur existe toujours en base
if (!$user) {
    // L'utilisateur n'existe plus en base, on détruit la session
    session_destroy();
    header('Location: /login.php?error=user_not_found');
    exit;
}

// Récupération des cumuls du mois en cours
$current_month = date('m');
$current_year = date('Y');

// Récupération des cumuls mensuels
$cumuls_query = "SELECT 
    SUM(CASE WHEN type_temps = 'heure_supplementaire' AND statut = 'validé' THEN duree_calculee ELSE 0 END) as total_hs,
    SUM(CASE WHEN type_temps = 'recuperation' AND statut = 'validé' THEN duree_calculee ELSE 0 END) as total_recup
FROM heures_supplementaires 
WHERE user_id = ? 
AND MONTH(date_jour) = ? 
AND YEAR(date_jour) = ?";

$stmt = $db->prepare($cumuls_query);
$stmt->execute([$_SESSION['user_id'], $current_month, $current_year]);
$cumuls = $stmt->fetch(PDO::FETCH_ASSOC);

// Si pas de résultats, initialiser à 0
if (!$cumuls) {
    $cumuls = [
        'total_hs' => 0,
        'total_recup' => 0
    ];
}

// Récupération des données du mois en cours
$current_month = date('m');
$current_year = date('Y');

// Récupération ou calcul du cumul global
$cumul_global = $cumulModel->getCumulUtilisateur($_SESSION['user_id']);
if (!$cumul_global) {
    $cumulModel->calculerCumul($_SESSION['user_id']);
    $cumul_global = $cumulModel->getCumulUtilisateur($_SESSION['user_id']);
}

// Initialisation des cumuls globaux avec des valeurs par défaut
$cumuls_globaux = [
    'total_hs' => 0,
    'total_recup' => 0
];

// Si nous avons un cumul global, mettre à jour les valeurs
if ($cumul_global) {
    $cumuls_globaux = [
        'total_hs' => $cumul_global['total_heures_sup'] ?? 0,
        'total_recup' => $cumul_global['total_heures_recup'] ?? 0
    ];
}

// Récupération des déclarations récentes (dernières 5)
$recent_declarations_query = "SELECT 
    h.*,
    c.nom as client_nom
FROM heures_supplementaires h
LEFT JOIN clients c ON h.client_id = c.id
WHERE h.user_id = ?
ORDER BY h.date_jour DESC, h.heure_debut DESC
LIMIT 5";

$stmt = $db->prepare($recent_declarations_query);
$stmt->execute([$_SESSION['user_id']]);
$recent_declarations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des cumuls par semaine du mois en cours
$weekly_cumuls_query = "SELECT 
    WEEK(date_jour, 3) as semaine,
    SUM(CASE WHEN type_temps = 'heure_supplementaire' THEN duree_calculee ELSE 0 END) as hs_semaine,
    SUM(CASE WHEN type_temps = 'recuperation' THEN duree_calculee ELSE 0 END) as recup_semaine
FROM heures_supplementaires 
WHERE user_id = ? 
AND MONTH(date_jour) = ? 
AND YEAR(date_jour) = ?
GROUP BY WEEK(date_jour, 3)
ORDER BY semaine";

$stmt = $db->prepare($weekly_cumuls_query);
$stmt->execute([$_SESSION['user_id'], $current_month, $current_year]);
$weekly_cumuls = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des permissions de l'utilisateur
$role = $_SESSION['user_role'];
$query = "SELECT page_code, has_access FROM role_permissions WHERE role = :role";
$stmt = $db->prepare($query);
$stmt->execute([':role' => $role]);
$permissions = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $permissions[$row['page_code']] = $row['has_access'];
}

// Récupération des déclarations en attente uniquement si l'utilisateur a les permissions
$declarations_en_attente = [];
if ($permissions['validation'] ?? false) {
    $sql = "SELECT 
                h.*,
                u.nom,
                u.prenom,
                c.nom as client_nom,
                h.commentaire_administratif,
                h.commentaire_date,
                admin.nom as admin_nom,
                admin.prenom as admin_prenom,
                h.type_temps,
                h.duree_calculee,
                h.date_jour,
                h.heure_debut,
                h.heure_fin,
                h.motif,
                h.commentaire,
                h.commentaire_date as commentaire_salarie_date
            FROM heures_supplementaires h
            JOIN users u ON h.user_id = u.id
            LEFT JOIN clients c ON h.client_id = c.id
            LEFT JOIN users admin ON h.commentaire_user_id = admin.id
            WHERE h.statut = 'en_attente'
            ORDER BY h.date_jour DESC, h.heure_debut DESC
            LIMIT 5";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $declarations_en_attente = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Inclusion du header
include APP_PATH . '/views/layouts/header.php';
?>

<div class="container-fluid py-4">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo htmlspecialchars($_SESSION['success']);
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($permissions['validation'] ?? false): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Administration</h5>
                    <div>
                        <?php if ($permissions['validation'] ?? false): ?>
                        <a href="/heures/validation.php" class="btn btn-primary me-2">
                            <i class="bi bi-check-circle"></i> Validation des heures
                        </a>
                        <?php endif; ?>
                        <?php if ($permissions['configuration'] ?? false): ?>
                        <button type="button" class="btn btn-warning" id="recalculer-cumuls" onclick="recalculerCumuls()">
                            <i class="bi bi-arrow-clockwise"></i> Recalculer les cumuls
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <h6 class="card-subtitle mb-3">Dernières demandes en attente</h6>
                    <?php if (empty($declarations_en_attente)): ?>
                    <div class="empty-state">
                        <i class="bi bi-check-circle"></i>
                        <p>Aucune déclaration en attente</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Utilisateur</th>
                                    <th>Client</th>
                                    <th>Type</th>
                                    <th>Durée</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($declarations_en_attente as $declaration): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($declaration['date_jour'])); ?></td>
                                    <td><?php echo htmlspecialchars($declaration['prenom'] . ' ' . $declaration['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($declaration['client_nom'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($declaration['type_temps'] === 'heure_supplementaire'): ?>
                                            <span class="badge bg-primary">Heure supplémentaire</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Récupération</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($declaration['duree_calculee'], 1); ?> h</td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($permissions['validation'] ?? false): ?>
                                            <button onclick="validerDeclaration(<?php echo $declaration['id']; ?>)" class="btn btn-sm btn-success">
                                                <i class="bi bi-check"></i>
                                            </button>
                                            <button onclick="refuserDeclaration(<?php echo $declaration['id']; ?>)" class="btn btn-sm btn-danger">
                                                <i class="bi bi-x"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($_SESSION['user_role'] === 'administratif' || $_SESSION['user_role'] === 'admin'): ?>
                                            <button type="button" class="btn btn-sm btn-info" onclick="ajouterCommentaire(<?php echo $declaration['id']; ?>)">
                                                <i class="bi bi-chat"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php if (!empty($declaration['commentaire'])): ?>
                                <tr class="comment-row">
                                    <td colspan="6">
                                        <div class="comment-box comment-salarie">
                                            <div class="comment-header">
                                                <i class="bi bi-person-circle"></i>
                                                <strong>Commentaire du salarié</strong>
                                                <small class="text-muted">
                                                    par <?php echo htmlspecialchars($declaration['prenom'] . ' ' . $declaration['nom']); ?>
                                                    le <?php echo date('d/m/Y H:i', strtotime($declaration['commentaire_salarie_date'])); ?>
                                                </small>
                                            </div>
                                            <div class="comment-content">
                                                <?php echo nl2br(htmlspecialchars($declaration['commentaire'])); ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($declaration['commentaire_administratif'])): ?>
                                <tr class="comment-row">
                                    <td colspan="6">
                                        <div class="comment-box comment-admin">
                                            <div class="comment-header">
                                                <i class="bi bi-chat-left-text"></i>
                                                <strong>Commentaire administratif</strong>
                                                <small class="text-muted">
                                                    par <?php echo htmlspecialchars($declaration['admin_prenom'] . ' ' . $declaration['admin_nom']); ?>
                                                    le <?php echo date('d/m/Y H:i', strtotime($declaration['commentaire_date'])); ?>
                                                </small>
                                            </div>
                                            <div class="comment-content">
                                                <?php echo nl2br(htmlspecialchars($declaration['commentaire_administratif'])); ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Modal pour l'ajout de commentaire -->
                    <div class="modal fade" id="commentaireModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Ajouter un commentaire administratif</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="commentaireForm">
                                        <input type="hidden" id="declaration_id" name="declaration_id">
                                        <div class="mb-3">
                                            <label for="commentaire" class="form-label">Commentaire</label>
                                            <textarea class="form-control" id="commentaire" name="commentaire" rows="3" required></textarea>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <button type="button" class="btn btn-primary" onclick="soumettreCommentaire()">Enregistrer</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (count($declarations_en_attente) === 5): ?>
                    <div class="text-center mt-3">
                        <a href="/heures/validation.php" class="btn btn-outline-primary btn-sm">
                            Voir toutes les demandes
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Vue d'ensemble -->
    <div class="row mb-4">


        <!-- Solde global -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Solde global</h5>
                </div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="text-center">
                        <i class="bi bi-clock-history solde-icon mb-2"></i>
                        <h6 class="text-muted mb-2">Solde d'heures disponibles</h6>
                        <h2 class="mb-0 <?php echo ($cumul_global && $cumul_global['solde_actuel'] < 0) ? 'text-danger' : 'text-success'; ?>">
                            <?php 
                            $solde = $cumul_global ? $cumul_global['solde_actuel'] : 0;
                            $heures = floor(abs($solde));
                            $minutes = round((abs($solde) - $heures) * 60);
                            echo ($solde < 0 ? '-' : '') . $heures . 'h' . ($minutes > 0 ? sprintf('%02d', $minutes) : '00');
                            ?>
                        </h2>
                        <small class="text-muted d-block">
                            (Heures supplémentaires - Récupérations)
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cumuls globaux -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Cumuls globaux</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center h-100">
                        <div class="col-6">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-clock cumuls-icon"></i>
                                <h6 class="text-muted mb-0">Heures supp.</h6>
                            </div>
                            <h3 class="mb-0"><?php echo number_format($cumuls_globaux['total_hs'], 1); ?> h</h3>
                        </div>
                        <div class="col-6 text-end">
                            <div class="d-flex align-items-center justify-content-end mb-2">
                                <h6 class="text-muted mb-0">Récupérations</h6>
                                <i class="bi bi-arrow-repeat cumuls-icon ms-2"></i>
                            </div>
                            <h3 class="mb-0"><?php echo number_format($cumuls_globaux['total_recup'], 1); ?> h</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cumuls du mois -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Cumuls du mois</h5>
                </div>
                <div class="card-body">
                    <div class="row align-items-center h-100">
                        <div class="col-6">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-clock cumuls-icon"></i>
                                <h6 class="text-muted mb-0">Heures supp.</h6>
                            </div>
                            <h3 class="mb-0"><?php echo number_format($cumuls['total_hs'], 1); ?> h</h3>
                        </div>
                        <div class="col-6 text-end">
                            <div class="d-flex align-items-center justify-content-end mb-2">
                                <h6 class="text-muted mb-0">Récupérations</h6>
                                <i class="bi bi-arrow-repeat cumuls-icon ms-2"></i>
                            </div>
                            <h3 class="mb-0"><?php echo number_format($cumuls['total_recup'], 1); ?> h</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progression hebdomadaire -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Progression hebdomadaire</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($weekly_cumuls)): ?>
                    <div class="empty-state">
                        <i class="bi bi-calendar-week"></i>
                        <p>Aucune heure déclarée pour ce mois</p>
                        <a href="/heures/ajouter.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Déclarer des heures
                        </a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($weekly_cumuls as $week): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Semaine <?php echo $week['semaine']; ?></span>
                            <span><?php echo number_format($week['hs_semaine'], 1); ?> h</span>
                        </div>
                        <div class="weekly-progress">
                            <div class="weekly-progress-bar" 
                                 style="width: <?php echo min(100, ($week['hs_semaine'] / 5) * 100); ?>%">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-3">
                        <a href="/heures/ajouter.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Déclarer des heures
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Déclarations récentes -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Déclarations récentes</h5>
                    <a href="/heures/historique.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Type</th>
                                    <th>Durée</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_declarations as $declaration): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($declaration['date_jour'])); ?></td>
                                    <td><?php echo htmlspecialchars($declaration['client_nom']); ?></td>
                                    <td>
                                        <?php if ($declaration['type_temps'] === 'heure_supplementaire'): ?>
                                            <span class="badge bg-primary">Heure supp</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Récup</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($declaration['duree_calculee'], 1); ?> h</td>
                                    <td>
                                        <?php if ($declaration['statut'] === 'en_attente'): ?>
                                            <span class="status-badge en-attente">En attente</span>
                                            <a href="/heures/modifier.php?id=<?php echo $declaration['id']; ?>" class="text-primary text-decoration-none">
                                                <i class="bi bi-pencil action-icon" title="Modifier"></i>
                                            </a>
                                            <i class="bi bi-trash action-icon" title="Supprimer"></i>
                                        <?php elseif ($declaration['statut'] === 'validé'): ?>
                                            <span class="status-badge valide">Validé</span>
                                        <?php elseif ($declaration['statut'] === 'rejeté'): ?>
                                            <span class="status-badge rejete">Rejeté</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


</div>

<style>
    :root {
        --msi-primary: #006B5F;
        --msi-primary-hover: #008374;
        --msi-secondary: #00D1E8;
        --msi-hs: #0D6EFD;
        --msi-recup: #198754;
        --card-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .card {
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid rgba(0,0,0,.125);
        border-radius: 15px 15px 0 0 !important;
    }

    .card-body {
        padding: 1.25rem;
    }

    .solde-icon {
        font-size: 2rem;
        color: var(--msi-primary);
        margin-bottom: 1rem;
    }

    .cumuls-icon {
        color: var(--msi-primary);
        margin-right: 0.5rem;
        font-size: 1rem;
    }

    .text-muted {
        color: #6c757d !important;
        font-size: 0.9rem;
    }

    h2 {
        font-size: 2.5rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    h3 {
        color: var(--msi-primary);
        font-size: 1.5rem;
        font-weight: 600;
    }

    .progress {
        height: 0.75rem;
        border-radius: 1rem;
        background-color: #e9ecef;
        margin-top: 1rem;
    }

    .progress-bar {
        border-radius: 1rem;
        transition: width 0.3s ease;
    }

    .weekly-progress {
        height: 20px;
        background-color: #e9ecef;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 5px;
    }

    .weekly-progress-bar {
        height: 100%;
        background-color: #0d6efd;
        transition: width 0.3s ease;
    }

    .btn-primary {
        background-color: var(--msi-primary);
        border-color: var(--msi-primary);
    }

    .btn-primary:hover {
        background-color: var(--msi-primary-hover);
        border-color: var(--msi-primary-hover);
    }

    .empty-state {
        text-align: center;
        padding: 2.5rem 2rem;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 2rem;
        margin-bottom: 1rem;
        color: var(--msi-primary);
        opacity: 0.8;
    }

    .empty-state p {
        margin-bottom: 1rem;
        font-size: 0.9rem;
        color: #6c757d;
    }

    .empty-state .btn-primary, 
    .text-center .btn-primary {
        padding: 0.4rem 1rem;
        font-size: 0.85rem;
        border-radius: 6px;
        transition: all 0.2s ease;
        background-color: var(--msi-primary);
        border-color: var(--msi-primary);
    }

    .empty-state .btn-primary:hover,
    .text-center .btn-primary:hover {
        background-color: var(--msi-primary-hover);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,107,95,0.2);
    }

    .empty-state .btn-primary i,
    .text-center .btn-primary i {
        font-size: 0.85rem;
        margin-right: 0.4rem;
    }

    .table {
        margin-bottom: 0;
    }

    .table th {
        font-weight: 600;
        color: var(--msi-primary);
        border-top: none;
        padding: 1rem;
        background-color: rgba(0,107,95,0.03);
    }

    .table td {
        padding: 1rem;
        vertical-align: middle;
    }

    .table tr:hover {
        background-color: rgba(0,107,95,0.02);
    }

    .badge {
        padding: 0.5em 0.8em;
        border-radius: 6px;
        font-weight: 500;
    }

    .btn-icon {
        padding: 0.4rem;
        border-radius: 6px;
        margin-left: 0.5rem;
        transition: all 0.2s ease;
    }

    .btn-icon:hover {
        background-color: rgba(0,107,95,0.1);
    }

    .voir-tout {
        color: var(--msi-primary);
        text-decoration: none;
        font-size: 0.9rem;
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .voir-tout:hover {
        background-color: rgba(0,107,95,0.1);
        text-decoration: none;
    }

    .quick-actions {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
    }

    .quick-action-btn {
        flex: 1;
        padding: 15px;
        border-radius: 10px;
        text-align: center;
        text-decoration: none;
        color: white;
        transition: transform 0.2s, box-shadow 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .quick-action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        color: white;
    }

    .quick-action-btn i {
        font-size: 1.2em;
    }

    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8em;
    }

    .status-badge.en-attente {
        background-color: #FFA500;
        color: white;
    }

    .status-badge.valide {
        background-color: var(--msi-primary);
        color: white;
    }

    .status-badge.rejete {
        background-color: #C4171E;
        color: white;
    }

    .action-icon {
        cursor: pointer;
        color: var(--msi-primary);
        margin-left: 0.5rem;
    }

    .card {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .card-header {
        background-color: white;
        border-bottom: 2px solid var(--msi-primary);
    }

    .table-responsive::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background: var(--msi-primary);
        border-radius: 3px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: var(--msi-secondary);
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
    }

    .btn-info {
        background-color: var(--msi-secondary);
        border-color: var(--msi-secondary);
        color: white;
    }

    .btn-info:hover {
        background-color: #5BA6B4;
        border-color: #5BA6B4;
        color: white;
    }

    .modal-header {
        background-color: var(--msi-primary);
        color: white;
    }

    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }

    .comment-row {
        background-color: transparent;
        border-left: 3px solid #e9ecef;
        margin: 0;
        padding: 0;
    }
    .comment-box {
        padding: 4px 8px 2px 8px;
        margin: 0;
        background-color: transparent;
        border-radius: 0;
    }
    .comment-box.comment-salarie {
        border-left: 3px solid #198754;
        margin-left: -3px;
    }
    .comment-box.comment-admin {
        border-left: 3px solid #0dcaf0;
        margin-left: -3px;
    }
    .comment-header {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 0;
        color: #6c757d;
        font-size: 0.85rem;
        line-height: 1;
    }
    .comment-header i {
        color: #0dcaf0;
        font-size: 0.85rem;
    }
    .comment-box.comment-salarie .comment-header i {
        color: #198754;
    }
    .comment-content {
        padding-left: 18px;
        margin-top: -2px;
        white-space: pre-wrap;
        font-size: 0.9rem;
        line-height: 1.2;
        color: #333;
    }
    .comment-header strong {
        font-size: 0.85rem;
        font-weight: 500;
    }
    .comment-header small {
        font-size: 0.8rem;
        color: #888;
    }

    /* Style pour la ligne de déclaration */
    .table > tbody > tr > td {
        padding: 8px;
        vertical-align: middle;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const declarationModal = document.getElementById('declarationModal');
    
    // Chargement du contenu du modal lors de son ouverture
    declarationModal.addEventListener('show.bs.modal', function() {
        fetch('/heures/declaration.php', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            this.querySelector('.modal-dialog').innerHTML = html;
            // Exécuter le code d'initialisation après l'insertion du contenu
            const typeSelect = document.getElementById('type');
            const clientSelect = document.getElementById('client_id');
            const motifSelect = document.getElementById('motif');

            function handleTypeChange() {
                if (typeSelect.value === 'recuperation') {
                    clientSelect.value = document.querySelector(`option[data-is-msi="1"]`).value;
                    clientSelect.disabled = true;
                    clientSelect.style.backgroundColor = '#e9ecef';
                    
                    motifSelect.value = 'demande_recuperation';
                    motifSelect.disabled = true;
                    motifSelect.style.backgroundColor = '#e9ecef';
} else {
                    clientSelect.disabled = false;
                    clientSelect.style.backgroundColor = '';
                    motifSelect.disabled = false;
                    motifSelect.style.backgroundColor = '';
                    motifSelect.value = '';
                }
            }

            typeSelect.addEventListener('change', handleTypeChange);
            // Exécution immédiate pour l'initialisation
            handleTypeChange();
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur est survenue lors du chargement du formulaire');
        });
    });
});

function validerDeclaration(id) {
    if (confirm('Êtes-vous sûr de vouloir valider cette déclaration ?')) {
        window.location.href = `/heures/validation.php?action=valider&id=${id}`;
    }
}

function refuserDeclaration(id) {
    if (confirm('Êtes-vous sûr de vouloir refuser cette déclaration ?')) {
        window.location.href = `/heures/validation.php?action=refuser&id=${id}`;
    }
}

let commentaireModal;
document.addEventListener('DOMContentLoaded', function() {
    commentaireModal = new bootstrap.Modal(document.getElementById('commentaireModal'));
});

function ajouterCommentaire(id) {
    document.getElementById('declaration_id').value = id;
    commentaireModal.show();
}

function soumettreCommentaire() {
    const formData = new FormData(document.getElementById('commentaireForm'));
    
    fetch('/heures/ajouter_commentaire.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Commentaire ajouté avec succès');
            commentaireModal.hide();
            // Recharger la page pour voir les mises à jour
            location.reload();
        } else {
            alert('Erreur lors de l\'ajout du commentaire : ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Une erreur est survenue lors de l\'ajout du commentaire');
    });
}

// Fonction pour recalculer les cumuls
function recalculerCumuls() {
    if (!confirm('Êtes-vous sûr de vouloir recalculer les cumuls ? Cette opération peut prendre quelques instants.')) {
        return;
    }

    // Désactiver le bouton pendant le traitement
    const btnRecalculer = document.getElementById('recalculer-cumuls');
    const originalText = btnRecalculer.innerHTML;
    btnRecalculer.disabled = true;
    btnRecalculer.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Recalcul en cours...';

    // Appeler le script de recalcul avec le bon chemin
    fetch('/admin/recalcul_cumuls.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Les cumuls ont été recalculés avec succès !');
                // Recharger la page pour afficher les nouvelles données
                window.location.reload();
            } else {
                alert('Erreur lors du recalcul des cumuls : ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur est survenue lors du recalcul des cumuls.');
        })
        .finally(() => {
            // Réactiver le bouton
            btnRecalculer.disabled = false;
            btnRecalculer.innerHTML = originalText;
        });
}
</script>

<?php
// Inclusion du footer
include APP_PATH . '/views/layouts/footer.php'; 
?> 