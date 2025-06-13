<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/HeureSupplementaire.php';
require_once APP_PATH . '/models/CumulUtilisateur.php';
require_once APP_PATH . '/helpers/auth_helper.php';

session_start();

// Vérification des permissions
checkPageAccess('validation');

$error = '';
$success = '';

// Traitement des actions de validation/rejet/commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = (new Database())->getConnection();
    $heureModel = new HeureSupplementaire($db);
    $cumulModel = new CumulUtilisateur($db);

    $id = $_POST['id'] ?? null;
    $action = $_POST['action'] ?? '';
    $commentaire = $_POST['commentaire'] ?? '';
    $commentaire_admin = $_POST['commentaire_admin'] ?? '';

    if ($id && $action) {
        try {
            if ($action === 'commentaire_admin') {
                // Mise à jour du commentaire administratif
                $query = "UPDATE heures_supplementaires 
                         SET commentaire_admin = :commentaire_admin 
                         WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':commentaire_admin', $commentaire_admin);
                $stmt->bindParam(':id', $id);
                if ($stmt->execute()) {
                    $success = "Le commentaire administratif a été enregistré avec succès.";
                }
            } elseif ($action === 'valider') {
                // Récupération des heures de la semaine
                $query = "SELECT date_jour, duree_calculee 
                         FROM heures_supplementaires 
                         WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $heure = $stmt->fetch(PDO::FETCH_ASSOC);

                // Calcul des heures de la semaine
                $debut_semaine = date('Y-m-d', strtotime('monday this week', strtotime($heure['date_jour'])));
                $fin_semaine = date('Y-m-d', strtotime('sunday this week', strtotime($heure['date_jour'])));
                
                $query = "SELECT SUM(duree_calculee) as total 
                         FROM heures_supplementaires 
                         WHERE user_id = (SELECT user_id FROM heures_supplementaires WHERE id = :id)
                         AND date_jour BETWEEN :debut_semaine AND :fin_semaine
                         AND type_temps = 'heure_supplementaire'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->bindParam(':debut_semaine', $debut_semaine);
                $stmt->bindParam(':fin_semaine', $fin_semaine);
                $stmt->execute();
                $heures_semaine = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

                // Calcul des majorations
                $majorations = $heureModel->calculateMajorations($heure['duree_calculee'], $heures_semaine);

                // Mise à jour avec les majorations
                if ($heureModel->updateStatus($id, 'validé', $commentaire, $majorations)) {
                    // Si un commentaire administratif est fourni, on le sauvegarde
                    if (!empty($commentaire_admin)) {
                        $query = "UPDATE heures_supplementaires 
                                 SET commentaire_admin = :commentaire_admin 
                                 WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':commentaire_admin', $commentaire_admin);
                        $stmt->bindParam(':id', $id);
                        $stmt->execute();
                    }
                    $success = "La demande a été validée avec succès.";

                    // Récupération de l'utilisateur concerné
                    $query = "SELECT user_id FROM heures_supplementaires WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$id]);
                    $declaration = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($declaration) {
                        // Mise à jour des cumuls de l'utilisateur
                        $cumulModel->calculerCumul($declaration['user_id']);
                    }
                }
            } elseif ($action === 'rejeter') {
                if ($heureModel->updateStatus($id, 'rejeté', $commentaire)) {
                    // Si un commentaire administratif est fourni, on le sauvegarde
                    if (!empty($commentaire_admin)) {
                        $query = "UPDATE heures_supplementaires 
                                 SET commentaire_admin = :commentaire_admin 
                                 WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':commentaire_admin', $commentaire_admin);
                        $stmt->bindParam(':id', $id);
                        $stmt->execute();
                    }
                    $success = "La demande a été rejetée.";
                }
            }
        } catch (Exception $e) {
            $error = "Erreur lors du traitement : " . $e->getMessage();
        }
    }
}

// Récupération des demandes en attente
$db = (new Database())->getConnection();
$heureModel = new HeureSupplementaire($db);
$demandes = $heureModel->getPendingValidations($_SESSION['user_id']);

require_once APP_PATH . '/views/layouts/header.php';
?>

<style>
    .status-badge {
        font-size: 0.9em;
    }
    .commentaire-admin {
        background-color: #fff3cd;
        border: 1px solid #ffeeba;
        border-radius: 4px;
        padding: 0.5rem;
        font-weight: 500;
        color: #856404;
    }
    .commentaire-admin:empty {
        display: none;
    }
</style>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">Validation des heures supplémentaires</h2>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (empty($demandes)): ?>
                <div class="alert alert-info">Aucune demande en attente de validation.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Employé</th>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Début</th>
                                <th>Fin</th>
                                <th>Durée</th>
                                <th>Type</th>
                                <th>Motif</th>
                                <th>Commentaire</th>
                                <th>Commentaire administratif</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($demandes as $demande): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($demande['date_jour'])); ?></td>
                                    <td><?php echo htmlspecialchars($demande['client_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($demande['heure_debut']); ?></td>
                                    <td><?php echo htmlspecialchars($demande['heure_fin']); ?></td>
                                    <td><?php echo number_format($demande['duree_calculee'], 2); ?>h</td>
                                    <td>
                                        <?php if ($demande['type_temps'] === 'recuperation'): ?>
                                            <span class="badge bg-warning">Récupération</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Heure supplémentaire</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($demande['motif']); ?></td>
                                    <td><?php echo htmlspecialchars($demande['commentaire'] ?? ''); ?></td>
                                    <td>
                                        <?php if (!empty($demande['commentaire_admin'])): ?>
                                            <div class="commentaire-admin">
                                                <?php echo htmlspecialchars($demande['commentaire_admin']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="validerDemande(<?php echo $demande['id']; ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="rejeterDemande(<?php echo $demande['id']; ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <?php if ($_SESSION['user_role'] === 'administratif'): ?>
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        onclick="editerDemande(<?php echo $demande['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de validation -->
<div class="modal fade" id="validationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="id" id="validation_id">
                <input type="hidden" name="action" id="validation_action">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Validation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
                    </div>
                    <?php if ($_SESSION['user_role'] === 'administratif'): ?>
                        <div class="mb-3">
                            <label for="commentaire_admin" class="form-label">Commentaire administratif</label>
                            <textarea class="form-control" id="commentaire_admin" name="commentaire_admin" rows="3"></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="confirmButton">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de commentaire administratif -->
<div class="modal fade" id="commentaireAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="id" id="commentaire_admin_id">
                <input type="hidden" name="action" value="commentaire_admin">
                
                <div class="modal-header">
                    <h5 class="modal-title">Commentaire Administratif</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="commentaire_admin_text" class="form-label">Commentaire administratif</label>
                        <textarea class="form-control" id="commentaire_admin_text" name="commentaire_admin" rows="3" 
                            placeholder="Ex: Attention, je n'ai pas réceptionné de document attestant de l'heure supplémentaire demandée."></textarea>
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

<script>
let validationModal;
let commentaireAdminModal;

document.addEventListener('DOMContentLoaded', function() {
    validationModal = new bootstrap.Modal(document.getElementById('validationModal'));
    commentaireAdminModal = new bootstrap.Modal(document.getElementById('commentaireAdminModal'));
});

function validerDemande(id) {
    document.getElementById('validation_id').value = id;
    document.getElementById('validation_action').value = 'valider';
    document.getElementById('modalTitle').textContent = 'Valider la demande';
    document.getElementById('confirmButton').className = 'btn btn-success';
    document.getElementById('confirmButton').textContent = 'Valider';
    validationModal.show();
}

function rejeterDemande(id) {
    document.getElementById('validation_id').value = id;
    document.getElementById('validation_action').value = 'rejeter';
    document.getElementById('modalTitle').textContent = 'Rejeter la demande';
    document.getElementById('confirmButton').className = 'btn btn-danger';
    document.getElementById('confirmButton').textContent = 'Rejeter';
    validationModal.show();
}

function editerDemande(id) {
    document.getElementById('commentaire_admin_id').value = id;
    commentaireAdminModal.show();
}
</script>

<?php require_once APP_PATH . '/views/layouts/footer.php'; ?> 