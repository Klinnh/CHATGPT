<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Correction des chemins
define('ROOT_PATH', dirname(dirname(dirname(dirname(__DIR__)))));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/helpers/auth_helper.php';

session_start();

// Vérification des permissions
checkPageAccess('users');

$db = (new Database())->getConnection();
$userModel = new User($db);

$error = '';
$success = '';
$user = null;

// Vérification de l'ID utilisateur
if (!isset($_GET['id'])) {
    header('Location: /admin/users.php?error=missing_id');
    exit;
}

$userId = $_GET['id'];

// Récupération des données de l'utilisateur
try {
    $user = $userModel->readOne($userId);
    if (!$user) {
        header('Location: /admin/users.php?error=user_not_found');
        exit;
    }
} catch (Exception $e) {
    header('Location: /admin/users.php?error=database_error');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom' => $_POST['nom'] ?? '',
        'prenom' => $_POST['prenom'] ?? '',
        'email' => $_POST['email'] ?? '',
        'role' => $_POST['role'] ?? '',
        'service' => $_POST['service'] ?? '',
        'matricule' => $_POST['matricule'] ?? '',
        'date_embauche' => $_POST['date_embauche'] ?? '',
        'actif' => isset($_POST['actif']) ? 1 : 0
    ];

    // Vérification si l'email existe déjà (sauf pour l'utilisateur actuel)
    if ($userModel->emailExists($data['email']) && $user['email'] !== $data['email']) {
        $error = "Cette adresse email est déjà utilisée.";
    } else {
        try {
            // Mise à jour du mot de passe uniquement s'il est fourni
            if (!empty($_POST['password'])) {
                $userModel->updatePassword($userId, $_POST['password']);
            }

            if ($userModel->update($userId, $data)) {
                $success = "L'utilisateur a été mis à jour avec succès.";
                // Recharger les données de l'utilisateur
                $user = $userModel->readOne($userId);
            } else {
                $error = "Une erreur est survenue lors de la mise à jour.";
            }
        } catch (Exception $e) {
            $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}

require_once APP_PATH . '/views/layouts/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2 class="mb-0">Modifier l'utilisateur</h2>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nom" class="form-label">Nom *</label>
                    <input type="text" class="form-control" id="nom" name="nom" value="<?php echo htmlspecialchars($user['nom'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="prenom" class="form-label">Prénom *</label>
                    <input type="text" class="form-control" id="prenom" name="prenom" value="<?php echo htmlspecialchars($user['prenom'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Mot de passe (laisser vide pour ne pas modifier)</label>
                    <input type="password" class="form-control" id="password" name="password">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="role" class="form-label">Rôle *</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="user" <?php echo ($user['role'] ?? '') === 'user' ? 'selected' : ''; ?>>Utilisateur</option>
                        <option value="manager" <?php echo ($user['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>Manager</option>
                        <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="service" class="form-label">Service</label>
                    <input type="text" class="form-control" id="service" name="service" value="<?php echo htmlspecialchars($user['service'] ?? ''); ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="matricule" class="form-label">Matricule</label>
                    <input type="text" class="form-control" id="matricule" name="matricule" value="<?php echo htmlspecialchars($user['matricule'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="date_embauche" class="form-label">Date d'embauche</label>
                    <input type="date" class="form-control" id="date_embauche" name="date_embauche" value="<?php echo htmlspecialchars($user['date_embauche'] ?? ''); ?>">
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="actif" name="actif" <?php echo ($user['actif'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="actif">Compte actif</label>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="/admin/users.php" class="btn btn-secondary">Retour</a>
                <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
            </div>
        </form>
    </div>
</div>

<script>
// Validation des formulaires Bootstrap
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php require_once APP_PATH . '/views/layouts/footer.php'; ?> 