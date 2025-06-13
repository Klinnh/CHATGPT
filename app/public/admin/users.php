<?php
// Affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définition des constantes
define('ROOT_PATH', dirname(dirname(__DIR__)));
define('APP_PATH', ROOT_PATH);

// Démarrage de la session
session_start();

// Inclusion des fichiers nécessaires
require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/helpers/auth_helper.php';

// Vérification des permissions
checkPageAccess('users');

// Initialisation de la connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Récupération de tous les utilisateurs
$user = new User($db);
$users_stmt = $user->read();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement des messages de succès/erreur
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';

// Inclusion du header
include APP_PATH . '/views/layouts/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Gestion des Utilisateurs</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="/admin/users/create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nouvel Utilisateur
            </a>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Service</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['id']); ?></td>
                                <td><?php echo htmlspecialchars($u['nom']); ?></td>
                                <td><?php echo htmlspecialchars($u['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $u['role'] === 'admin' ? 'bg-danger' : ($u['role'] === 'manager' ? 'bg-warning' : 'bg-info'); ?>">
                                        <?php echo htmlspecialchars($u['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($u['service'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge <?php echo $u['actif'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $u['actif'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="/admin/users/edit.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmDelete(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer l'utilisateur <span id="userName"></span> ?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form action="/admin/users/delete.php" method="POST" class="d-inline">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('userName').textContent = userName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include APP_PATH . '/views/layouts/footer.php'; ?> 