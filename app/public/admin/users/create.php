<?php
// Affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définition des constantes
define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH);

// Démarrage de la session
session_start();

// Inclusion des fichiers nécessaires
require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/helpers/auth_helper.php';

// Vérification des permissions
checkPageAccess('users');

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    $user = new User($db);

    // Vérification si l'email existe déjà
    if ($user->emailExists($_POST['email'])) {
        $error = "Cet email est déjà utilisé.";
    } else {
        // Création de l'utilisateur
        $data = [
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'email' => $_POST['email'],
            'password' => $_POST['password'],
            'role' => $_POST['role'],
            'service' => $_POST['service'],
            'matricule' => $_POST['matricule'],
            'date_embauche' => $_POST['date_embauche'],
            'actif' => isset($_POST['actif']) ? 1 : 0
        ];

        if ($user->createUser($data)) {
            header("Location: /admin/users.php?success=created");
            exit();
        } else {
            $error = "Une erreur est survenue lors de la création de l'utilisateur.";
        }
    }
}

// Inclusion du header
include APP_PATH . '/views/layouts/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h2>Nouvel Utilisateur</h2>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nom" class="form-label">Nom</label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                            <div class="col-md-6">
                                <label for="prenom" class="form-label">Prénom</label>
                                <input type="text" class="form-control" id="prenom" name="prenom" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="role" class="form-label">Rôle</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="technicien">Technicien</option>
                                    <option value="manager">Manager</option>
                                    <option value="admin">Administrateur</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="service" class="form-label">Service</label>
                                <input type="text" class="form-control" id="service" name="service">
                            </div>
                            <div class="col-md-4">
                                <label for="matricule" class="form-label">Matricule</label>
                                <input type="text" class="form-control" id="matricule" name="matricule">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="date_embauche" class="form-label">Date d'embauche</label>
                                <input type="date" class="form-control" id="date_embauche" name="date_embauche">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="actif" name="actif" checked>
                                    <label class="form-check-label" for="actif">
                                        Compte actif
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <a href="/admin/users.php" class="btn btn-secondary">Annuler</a>
                                <button type="submit" class="btn btn-primary">Créer l'utilisateur</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
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

<?php include APP_PATH . '/views/layouts/footer.php'; ?> 