<?php
// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Récupération des données de l'utilisateur
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Récupération des données du mois en cours
$current_month = date('m');
$current_year = date('Y');

// Récupération des cumuls du mois
$cumuls_query = "SELECT 
    SUM(CASE WHEN type_temps = 'heure_supplementaire' THEN duree_calculee ELSE 0 END) as total_hs,
    SUM(CASE WHEN type_temps = 'recuperation' THEN duree_calculee ELSE 0 END) as total_recup
FROM heures_supplementaires 
WHERE user_id = ? 
AND MONTH(date_jour) = ? 
AND YEAR(date_jour) = ?
AND status = 'validé'";

$stmt = $pdo->prepare($cumuls_query);
$stmt->execute([$user_id, $current_month, $current_year]);
$cumuls = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupération des déclarations récentes (dernières 5)
$recent_declarations_query = "SELECT 
    h.*,
    c.nom as client_nom
FROM heures_supplementaires h
LEFT JOIN clients c ON h.client_id = c.id
WHERE h.user_id = ?
ORDER BY h.date_jour DESC, h.heure_debut DESC
LIMIT 5";

$stmt = $pdo->prepare($recent_declarations_query);
$stmt->execute([$user_id]);
$recent_declarations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des cumuls par semaine du mois en cours
$weekly_cumuls_query = "SELECT 
    WEEK(date_jour) as semaine,
    SUM(CASE WHEN type_temps = 'heure_supplementaire' THEN duree_calculee ELSE 0 END) as hs_semaine,
    SUM(CASE WHEN type_temps = 'recuperation' THEN duree_calculee ELSE 0 END) as recup_semaine
FROM heures_supplementaires 
WHERE user_id = ? 
AND MONTH(date_jour) = ? 
AND YEAR(date_jour) = ?
AND status = 'validé'
GROUP BY WEEK(date_jour)
ORDER BY semaine";

$stmt = $pdo->prepare($weekly_cumuls_query);
$stmt->execute([$user_id, $current_month, $current_year]);
$weekly_cumuls = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Gestion des heures supplémentaires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
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
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .quick-action-btn {
            flex: 1;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            color: white;
            transition: transform 0.2s;
        }
        .quick-action-btn:hover {
            transform: translateY(-2px);
            color: white;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
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
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- En-tête avec actions rapides -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="quick-actions">
                    <a href="/heures/declaration.php" class="quick-action-btn bg-primary">
                        <i class="bi bi-plus-circle"></i> Nouvelle déclaration
                    </a>
                    <a href="/heures/historique.php" class="quick-action-btn bg-info">
                        <i class="bi bi-clock-history"></i> Historique
                    </a>
                    <?php if ($user_role === 'admin'): ?>
                    <a href="/admin/recalculer_cumuls.php" class="quick-action-btn bg-warning">
                        <i class="bi bi-arrow-repeat"></i> Recalculer tous les cumuls
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Vue d'ensemble -->
        <div class="row">
            <!-- Cumuls du mois -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Cumuls du mois</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <h6 class="text-muted">Heures supplémentaires</h6>
                                <h3><?php echo number_format($cumuls['total_hs'], 1); ?> h</h3>
                            </div>
                            <div class="text-end">
                                <h6 class="text-muted">Récupérations</h6>
                                <h3><?php echo number_format($cumuls['total_recup'], 1); ?> h</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progression hebdomadaire -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Progression hebdomadaire</h5>
                    </div>
                    <div class="card-body">
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
                                                <span class="badge bg-primary">HS</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Récup</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($declaration['duree_calculee'], 1); ?> h</td>
                                        <td>
                                            <?php if ($declaration['status'] === 'validé'): ?>
                                                <span class="status-badge bg-success">Validé</span>
                                            <?php elseif ($declaration['status'] === 'en_attente'): ?>
                                                <span class="status-badge bg-warning">En attente</span>
                                            <?php else: ?>
                                                <span class="status-badge bg-danger">Refusé</span>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 