<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/config/database.php';

session_start();

// Vérification des droits admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php?error=unauthorized');
    exit;
}

$error = '';
$success = '';

$db = (new Database())->getConnection();

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("UPDATE parametres_temps SET valeur = ? WHERE code = ?");
        
        foreach ($_POST['params'] as $code => $valeur) {
            $stmt->execute([floatval($valeur), $code]);
        }
        
        $success = "Les paramètres ont été mis à jour avec succès.";
    } catch (Exception $e) {
        $error = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}

// Récupération des paramètres actuels
$stmt = $db->query("SELECT * FROM parametres_temps ORDER BY id");
$parametres = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once APP_PATH . '/views/layouts/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">Paramètres du temps de travail</h2>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <?php foreach ($parametres as $param): ?>
                    <div class="mb-3">
                        <label for="<?php echo htmlspecialchars($param['code']); ?>" class="form-label">
                            <?php echo htmlspecialchars($param['description']); ?>
                        </label>
                        <div class="input-group">
                            <input type="number" 
                                   class="form-control" 
                                   id="<?php echo htmlspecialchars($param['code']); ?>" 
                                   name="params[<?php echo htmlspecialchars($param['code']); ?>]" 
                                   value="<?php echo htmlspecialchars($param['valeur']); ?>"
                                   step="0.25"
                                   min="0"
                                   required>
                            <span class="input-group-text">heures</span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="d-flex justify-content-between">
                    <a href="/index.php" class="btn btn-secondary">Retour</a>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </form>
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

<?php require_once APP_PATH . '/views/layouts/footer.php'; ?> 