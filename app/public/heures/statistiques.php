<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/HeureSupplementaire.php';
require_once APP_PATH . '/helpers/auth_helper.php';

session_start();

// Vérification des permissions
checkPageAccess('statistiques');

$db = (new Database())->getConnection();
$heureModel = new HeureSupplementaire($db);

$error = '';
$success = '';

// Récupération des statistiques globales
try {
    $query = "SELECT 
                u.id,
                u.nom,
                u.prenom,
                u.service,
                COUNT(DISTINCT hs.id) as nombre_demandes,
                SUM(CASE WHEN hs.type_temps = 'heure_supplementaire' THEN hs.duree_calculee ELSE 0 END) as total_heures_supp,
                SUM(CASE WHEN hs.type_temps = 'recuperation' THEN hs.duree_calculee ELSE 0 END) as total_recuperations,
                SUM(hs.majoration_25) as total_majoration_25,
                SUM(hs.majoration_50) as total_majoration_50,
                SUM(hs.majoration_100) as total_majoration_100
             FROM users u
             LEFT JOIN heures_supplementaires hs ON u.id = hs.user_id AND hs.statut = 'validé'
             GROUP BY u.id, u.nom, u.prenom, u.service
             ORDER BY u.nom, u.prenom";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($stats === false) {
        throw new Exception("Erreur lors de la récupération des statistiques globales");
    }
} catch (Exception $e) {
    error_log("Erreur dans statistiques.php: " . $e->getMessage());
    $error = "Une erreur est survenue lors de la récupération des statistiques : " . $e->getMessage();
}

// Récupération des statistiques détaillées
if (isset($_GET['user_id']) && isset($_GET['periode'])) {
    try {
        $user_id = $_GET['user_id'];
        $periode = $_GET['periode'];
        $annee = $_GET['annee'] ?? date('Y');
        $mois = $_GET['mois'] ?? date('m');

        // Récupération des informations de l'utilisateur
        $query = "SELECT nom, prenom, service FROM users WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Utilisateur non trouvé");
        }

        // Récupération des heures selon la période
        $query = "SELECT 
                    DATE_FORMAT(date_jour, '%Y-%m') as mois,
                    type_temps,
                    SUM(CASE WHEN type_temps = 'heure_supplementaire' THEN duree_calculee ELSE 0 END) as total_heures_supp,
                    SUM(CASE WHEN type_temps = 'recuperation' THEN duree_calculee ELSE 0 END) as total_recuperations,
                    SUM(majoration_25) as majoration_25,
                    SUM(majoration_50) as majoration_50,
                    SUM(majoration_100) as majoration_100
                 FROM heures_supplementaires 
                 WHERE user_id = :user_id 
                 AND statut = 'validé'";

        $params = [':user_id' => $user_id];

        if ($periode === 'annuel') {
            $query .= " AND YEAR(date_jour) = :annee";
            $params[':annee'] = $annee;
        } else {
            $query .= " AND YEAR(date_jour) = :annee AND MONTH(date_jour) = :mois";
            $params[':annee'] = $annee;
            $params[':mois'] = $mois;
        }

        $query .= " GROUP BY DATE_FORMAT(date_jour, '%Y-%m')";

        try {
            $stmt = $db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($details === false) {
                throw new Exception("Erreur lors de la récupération des données : " . implode(", ", $stmt->errorInfo()));
            }

            // Calcul des totaux
            $totaux = [
                'heures_supplementaires' => 0,
                'recuperations' => 0,
                'majoration_25' => 0,
                'majoration_50' => 0,
                'majoration_100' => 0
            ];

            foreach ($details as $detail) {
                $totaux['heures_supplementaires'] += floatval($detail['total_heures_supp']);
                $totaux['recuperations'] += floatval($detail['total_recuperations']);
                $totaux['majoration_25'] += floatval($detail['majoration_25']);
                $totaux['majoration_50'] += floatval($detail['majoration_50']);
                $totaux['majoration_100'] += floatval($detail['majoration_100']);
            }

            // Récupération des paramètres de temps
            $query = "SELECT * FROM parametres_temps WHERE id = 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $parametres = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parametres) {
                throw new Exception("Paramètres de temps non trouvés");
            }

            // Calcul des seuils
            $seuils = [
                'heures_supplementaires' => $parametres['seuil_declenchement_heures_supp'],
                'majoration_25' => $parametres['seuil_majoration_heures_supp']
            ];

            // Si c'est une requête AJAX, retourner les données en JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'user' => $user,
                    'details' => $details,
                    'totaux' => $totaux,
                    'seuils' => $seuils,
                    'periode' => $periode,
                    'annee' => $annee,
                    'mois' => $mois
                ]);
                exit;
            }

        } catch (Exception $e) {
            error_log("Erreur dans statistiques.php: " . $e->getMessage());
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
                exit;
            }
            $error = "Une erreur est survenue lors de la récupération des statistiques détaillées : " . $e->getMessage();
        }
    } catch (Exception $e) {
        error_log("Erreur dans statistiques.php: " . $e->getMessage());
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
        $error = "Une erreur est survenue lors de la récupération des statistiques détaillées : " . $e->getMessage();
    }
}

require_once APP_PATH . '/views/layouts/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">Statistiques des heures supplémentaires</h2>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Service</th>
                            <th>Nombre de demandes</th>
                            <th>Total heures supplémentaires</th>
                            <th>Total récupérations</th>
                            <th>Majoration 25%</th>
                            <th>Majoration 50%</th>
                            <th>Majoration 100%</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats as $stat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['prenom'] . ' ' . $stat['nom']); ?></td>
                                <td><?php echo htmlspecialchars($stat['service']); ?></td>
                                <td><?php echo $stat['nombre_demandes']; ?></td>
                                <td><?php echo number_format($stat['total_heures_supp'], 2); ?>h</td>
                                <td><?php echo number_format($stat['total_recuperations'], 2); ?>h</td>
                                <td><?php echo number_format($stat['total_majoration_25'], 2); ?>h</td>
                                <td><?php echo number_format($stat['total_majoration_50'], 2); ?>h</td>
                                <td><?php echo number_format($stat['total_majoration_100'], 2); ?>h</td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-primary btn-period" 
                                                data-user-id="<?php echo $stat['id']; ?>"
                                                data-period="annuel"
                                                data-year="<?php echo date('Y'); ?>">
                                            Annuel
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info btn-period" 
                                                data-user-id="<?php echo $stat['id']; ?>"
                                                data-period="mensuel"
                                                data-year="<?php echo date('Y'); ?>"
                                                data-month="<?php echo date('m'); ?>">
                                            Mensuel
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (isset($user)): ?>
                <h3>Statistiques de <?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h3>
                <p>Service : <?php echo htmlspecialchars($user['service']); ?></p>
                <p>Période : <?php echo $periode === 'annuel' ? $annee : date('F Y', mktime(0, 0, 0, $mois, 1, $annee)); ?></p>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Heures supplémentaires</h4>
                            </div>
                            <div class="card-body">
                                <p>Total : <?php echo number_format($totaux['heures_supplementaires'], 2); ?>h</p>
                                <p>Majoration 25% : <?php echo number_format($totaux['majoration_25'], 2); ?>h</p>
                                <p>Majoration 50% : <?php echo number_format($totaux['majoration_50'], 2); ?>h</p>
                                <p>Majoration 100% : <?php echo number_format($totaux['majoration_100'], 2); ?>h</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Récupérations</h4>
                            </div>
                            <div class="card-body">
                                <p>Total : <?php echo number_format($totaux['recuperations'], 2); ?>h</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mt-4">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Mois</th>
                                <th>Type</th>
                                <th>Total heures</th>
                                <th>Total récupérations</th>
                                <th>Majoration 25%</th>
                                <th>Majoration 50%</th>
                                <th>Majoration 100%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($details as $detail): ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($detail['mois'] . '-01')); ?></td>
                                    <td>
                                        <?php if ($detail['type_temps'] === 'heure_supplementaire'): ?>
                                            <span class="badge bg-primary">Heure supplémentaire</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Récupération</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($detail['total_heures_supp'], 2); ?>h</td>
                                    <td><?php echo number_format($detail['total_recuperations'], 2); ?>h</td>
                                    <td><?php echo number_format($detail['majoration_25'], 2); ?>h</td>
                                    <td><?php echo number_format($detail['majoration_50'], 2); ?>h</td>
                                    <td><?php echo number_format($detail['majoration_100'], 2); ?>h</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Veuillez sélectionner un utilisateur et une période pour voir les statistiques.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="statsModal" tabindex="-1" aria-labelledby="statsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statsModalLabel">Statistiques détaillées</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="btn-group mb-3">
                    <button type="button" class="btn btn-primary btn-modal-period active" data-period="annuel">Annuel</button>
                    <button type="button" class="btn btn-info btn-modal-period" data-period="mensuel">Mensuel</button>
                </div>
                <div class="mb-3">
                    <select class="form-select d-inline-block w-auto" id="modalMonth" style="display: none;">
                        <option value="1">Janvier</option>
                        <option value="2">Février</option>
                        <option value="3">Mars</option>
                        <option value="4">Avril</option>
                        <option value="5">Mai</option>
                        <option value="6">Juin</option>
                        <option value="7">Juillet</option>
                        <option value="8">Août</option>
                        <option value="9">Septembre</option>
                        <option value="10">Octobre</option>
                        <option value="11">Novembre</option>
                        <option value="12">Décembre</option>
                    </select>
                    <select class="form-select d-inline-block w-auto" id="modalYear">
                        <?php 
                        $currentYear = date('Y');
                        for($y = $currentYear - 2; $y <= $currentYear; $y++) {
                            echo "<option value=\"$y\"" . ($y == $currentYear ? " selected" : "") . ">$y</option>";
                        }
                        ?>
                    </select>
                </div>
                <div id="modalContent">
                    <!-- Le contenu sera chargé dynamiquement ici -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_PATH . '/views/layouts/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentUserId = null;
    const statsModal = document.getElementById('statsModal');
    const modalMonth = document.getElementById('modalMonth');
    const modalYear = document.getElementById('modalYear');
    const btnModalPeriods = document.querySelectorAll('.btn-modal-period');
    const modalContent = document.getElementById('modalContent');
    const bsModal = new bootstrap.Modal(statsModal);

    // Fonction pour mettre à jour l'état actif des boutons de période
    function updateActiveButton(activeButton) {
        btnModalPeriods.forEach(btn => {
            btn.classList.remove('active');
        });
        activeButton.classList.add('active');
    }

    // Fonction pour mettre à jour les statistiques
    async function updateStats(userId, periode, annee, mois) {
        if (!userId) {
            console.error('Aucun utilisateur sélectionné');
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('user_id', userId);
        url.searchParams.set('periode', periode);
        url.searchParams.set('annee', annee);
        if (mois) {
            url.searchParams.set('mois', mois);
        }

        modalContent.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Chargement...</span></div></div>';

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Erreur réseau');
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Une erreur est survenue lors de la mise à jour des statistiques');
            }

            // Création du contenu HTML
            let detailsHtml = '';
            if (data.details && data.details.length > 0) {
                detailsHtml = data.details.map(detail => `
                    <tr>
                        <td>${new Date(detail.mois + '-01').toLocaleString('fr-FR', { month: 'long', year: 'numeric' })}</td>
                        <td>${parseFloat(detail.total_heures_supp || 0).toFixed(2)}h</td>
                        <td>${parseFloat(detail.total_recuperations || 0).toFixed(2)}h</td>
                        <td>${parseFloat(detail.majoration_25 || 0).toFixed(2)}h</td>
                        <td>${parseFloat(detail.majoration_50 || 0).toFixed(2)}h</td>
                        <td>${parseFloat(detail.majoration_100 || 0).toFixed(2)}h</td>
                    </tr>
                `).join('');
            } else {
                detailsHtml = '<tr><td colspan="6" class="text-center">Aucune donnée disponible pour cette période</td></tr>';
            }

            modalContent.innerHTML = `
                <h3>Statistiques de ${data.user.prenom} ${data.user.nom}</h3>
                <p>Service : ${data.user.service}</p>
                <p>Période : ${data.periode === 'annuel' ? data.annee : new Date(data.annee, data.mois - 1).toLocaleString('fr-FR', { month: 'long', year: 'numeric' })}</p>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Heures supplémentaires</h4>
                            </div>
                            <div class="card-body">
                                <p>Total : ${parseFloat(data.totaux.heures_supplementaires || 0).toFixed(2)}h</p>
                                <p>Majoration 25% : ${parseFloat(data.totaux.majoration_25 || 0).toFixed(2)}h</p>
                                <p>Majoration 50% : ${parseFloat(data.totaux.majoration_50 || 0).toFixed(2)}h</p>
                                <p>Majoration 100% : ${parseFloat(data.totaux.majoration_100 || 0).toFixed(2)}h</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Récupérations</h4>
                            </div>
                            <div class="card-body">
                                <p>Total : ${parseFloat(data.totaux.recuperations || 0).toFixed(2)}h</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mt-4">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Mois</th>
                                <th>Heures supplémentaires</th>
                                <th>Récupérations</th>
                                <th>Majoration 25%</th>
                                <th>Majoration 50%</th>
                                <th>Majoration 100%</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${detailsHtml}
                        </tbody>
                    </table>
                </div>
            `;

            return true;
        } catch (error) {
            console.error('Erreur:', error);
            modalContent.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
            return false;
        }
    }

    // Gestion des clics sur les boutons de période dans le tableau principal
    document.querySelectorAll('.btn-period').forEach(button => {
        button.addEventListener('click', async function(e) {
            e.preventDefault();
            currentUserId = this.dataset.userId;
            
            // S'assurer que le bouton annuel est actif et que le sélecteur de mois est caché
            const annualButton = document.querySelector('.btn-modal-period[data-period="annuel"]');
            if (annualButton) {
                updateActiveButton(annualButton);
                modalMonth.style.display = 'none';
            }
            
            // Précharger les données avant d'afficher le modal
            const success = await updateStats(currentUserId, 'annuel', modalYear.value);
            
            // Afficher le modal seulement si les données sont chargées avec succès
            if (success) {
                bsModal.show();
            }
        });
    });

    // Gestion des clics sur les boutons de période dans le modal
    btnModalPeriods.forEach(button => {
        button.addEventListener('click', function() {
            const periode = this.dataset.period;
            updateActiveButton(this);
            modalMonth.style.display = periode === 'mensuel' ? 'inline-block' : 'none';
            updateStats(currentUserId, periode, modalYear.value, periode === 'mensuel' ? modalMonth.value : null);
        });
    });

    // Gestion du changement d'année
    modalYear.addEventListener('change', function() {
        const activeButton = document.querySelector('.btn-modal-period.active');
        const periode = activeButton ? activeButton.dataset.period : 'annuel';
        updateStats(currentUserId, periode, this.value, periode === 'mensuel' ? modalMonth.value : null);
    });

    // Gestion du changement de mois
    modalMonth.addEventListener('change', function() {
        updateStats(currentUserId, 'mensuel', modalYear.value, this.value);
    });

    // Réinitialiser l'état du modal lors de sa fermeture
    statsModal.addEventListener('hidden.bs.modal', function() {
        const annualButton = document.querySelector('.btn-modal-period[data-period="annuel"]');
        if (annualButton) {
            updateActiveButton(annualButton);
            modalMonth.style.display = 'none';
        }
    });
});
</script> 