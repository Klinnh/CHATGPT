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

// Si l'utilisateur est déjà connecté, rediriger vers la page d'accueil
if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

// Inclusion des fichiers nécessaires
require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/User.php';

// Initialisation de la connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Vérification de la connexion
if (!$db) {
    die("Erreur de connexion à la base de données");
}

// Traitement du formulaire de connexion
$error = '';
$success = '';

// Gestion des messages
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'logout_success':
            $success = 'Vous avez été déconnecté avec succès.';
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        $user = new User($db);
        $user_data = $user->authenticate($email, $password);

        if ($user_data) {
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['user_role'] = $user_data['role'];
            $_SESSION['user_nom'] = $user_data['nom'];
            $_SESSION['user_prenom'] = $user_data['prenom'];
            header("Location: /index.php");
            exit();
        } else {
            $error = "Email ou mot de passe incorrect";
        }
    }
}

// Inclusion du header
include APP_PATH . '/views/layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card mt-5">
            <div class="card-header">
                <h2 class="text-center">Connexion</h2>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Se connecter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Inclusion du footer
include APP_PATH . '/views/layouts/footer.php';
?> 