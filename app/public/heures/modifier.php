<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/HeureSupplementaire.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php?error=not_logged_in');
    exit;
}

$db = (new Database())->getConnection();
$error = '';
$success = '';

// Vérification de l'ID
if (!isset($_GET['id'])) {
    header('Location: /index.php');
    exit;
}

// Récupération des données de la déclaration
$query = "SELECT h.*, c.nom as client_nom 
          FROM heures_supplementaires h
          LEFT JOIN clients c ON h.client_id = c.id
          WHERE h.id = ? AND h.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_GET['id'], $_SESSION['user_id']]);
$declaration = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$declaration) {
    header('Location: /index.php');
    exit;
}

// Récupération des clients
$query = "SELECT id, nom, code FROM clients WHERE actif = 1 ORDER BY nom";
$stmt = $db->prepare($query);
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $errors = [];
        
        if (empty($_POST['date'])) {
            $errors[] = "La date est requise";
        }
        
        if (empty($_POST['heure_debut']) || empty($_POST['heure_fin'])) {
            $errors[] = "Les heures de début et de fin sont requises";
        }
        
        // On vérifie le client seulement pour les heures supplémentaires
        if ($declaration['type_temps'] === 'heure_supplementaire' && empty($_POST['client_id'])) {
            $errors[] = "Le client est requis";
        }

        if (empty($errors)) {
            $heure = new HeureSupplementaire($db);
            
            // Calcul de la durée
            $debut = strtotime($_POST['date'] . ' ' . $_POST['heure_debut']);
            $fin = strtotime($_POST['date'] . ' ' . $_POST['heure_fin']);
            
            if ($fin < $debut) {
                $fin += 86400;
            }
            
            $duree = ($fin - $debut) / 3600;
            
            // Calcul des heures de la semaine
            $date = $_POST['date'];
            $debut_semaine = date('Y-m-d', strtotime('monday this week', strtotime($date)));
            $fin_semaine = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
            
            $query = "SELECT 
                COALESCE(SUM(CASE 
                    WHEN type_temps = 'heure_supplementaire' THEN duree_calculee 
                    WHEN type_temps = 'recuperation' THEN -duree_calculee 
                    ELSE 0 
                END), 0) as total_effectif
            FROM heures_supplementaires 
            WHERE user_id = :user_id
            AND date_jour BETWEEN :debut_semaine AND :fin_semaine
            AND id != :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':debut_semaine', $debut_semaine);
            $stmt->bindParam(':fin_semaine', $fin_semaine);
            $stmt->bindParam(':id', $_GET['id']);
            $stmt->execute();
            $heures_semaine = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total_effectif']);

            if ($declaration['type_temps'] === 'recuperation') {
                $majorations = [
                    'montants' => ['standard' => 0, 'superieur' => 0],
                    'taux_majoration_standard' => 0,
                    'taux_majoration_superieur' => 0
                ];
            } else {
                $majorations = $heure->calculateMajorations($duree, $heures_semaine);
            }

            $data = [
                'date_jour' => $_POST['date'],
                'client_id' => $_POST['client_id'],
                'heure_debut' => $_POST['heure_debut'],
                'heure_fin' => $_POST['heure_fin'],
                'duree_calculee' => $duree,
                'type_temps' => $declaration['type_temps'],
                'motif' => $_POST['motif'],
                'temps_pause' => 0,
                'commentaire' => $_POST['commentaire'] ?? null,
                'statut' => 'en_attente',
                'majoration_standard' => $majorations['montants']['standard'],
                'majoration_superieur' => $majorations['montants']['superieur'],
                'taux_majoration_standard' => $majorations['taux_majoration_standard'],
                'taux_majoration_superieur' => $majorations['taux_majoration_superieur']
            ];

            if ($heure->update($_GET['id'], $data)) {
                $_SESSION['success'] = "Les heures ont été mises à jour avec succès.";
                header('Location: /index.php');
                exit;
            } else {
                $error = "Une erreur est survenue lors de la mise à jour.";
            }
        } else {
            $error = implode('<br>', $errors);
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

require_once APP_PATH . '/views/layouts/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h2>Modifier la déclaration</h2>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="date" name="date" 
                                       value="<?php echo htmlspecialchars($declaration['date_jour']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Type de temps</label>
                                <input type="text" class="form-control" value="<?php echo $declaration['type_temps'] === 'heure_supplementaire' ? 'Heure supplémentaire' : 'Récupération'; ?>" readonly>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="heure_debut" class="form-label">Heure de début *</label>
                                <input type="time" class="form-control" id="heure_debut" name="heure_debut" 
                                       value="<?php echo htmlspecialchars($declaration['heure_debut']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="heure_fin" class="form-label">Heure de fin *</label>
                                <input type="time" class="form-control" id="heure_fin" name="heure_fin" 
                                       value="<?php echo htmlspecialchars($declaration['heure_fin']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="client_id" class="form-label">Client *</label>
                            <?php if ($declaration['type_temps'] === 'recuperation'): ?>
                                <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($declaration['client_id']); ?>">
                                <select class="form-select" disabled>
                            <?php else: ?>
                                <select class="form-select" id="client_id" name="client_id" required>
                            <?php endif; ?>
                                <option value="">Sélectionnez un client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>" 
                                            <?php echo $client['id'] == $declaration['client_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['nom']); ?>
                                        <?php if ($client['code']): ?>
                                            (<?php echo htmlspecialchars($client['code']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="motif" class="form-label">Motif *</label>
                            <?php if ($declaration['type_temps'] === 'recuperation'): ?>
                                <input type="hidden" name="motif" value="<?php echo htmlspecialchars($declaration['motif']); ?>">
                                <select class="form-select" disabled>
                            <?php else: ?>
                                <select class="form-select" id="motif" name="motif" required>
                            <?php endif; ?>
                                <option value="">Sélectionnez un motif</option>
                                <option value="surcharge" <?php echo $declaration['motif'] === 'surcharge' ? 'selected' : ''; ?>>Surcharge</option>
                                <option value="urgence" <?php echo $declaration['motif'] === 'urgence' ? 'selected' : ''; ?>>Urgence</option>
                                <option value="remplacement" <?php echo $declaration['motif'] === 'remplacement' ? 'selected' : ''; ?>>Remplacement</option>
                                <option value="projet" <?php echo $declaration['motif'] === 'projet' ? 'selected' : ''; ?>>Projet</option>
                                <option value="autre" <?php echo $declaration['motif'] === 'autre' ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="commentaire" class="form-label">Commentaire</label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="3"><?php echo htmlspecialchars($declaration['commentaire'] ?? ''); ?></textarea>
                        </div>

                        <div class="alert alert-info" id="duree_calculee">
                            Durée calculée : <strong><span id="duree_heures">0</span> heures</strong><br>
                            <?php if ($declaration['type_temps'] === 'heure_supplementaire'): ?>
                                Avec majoration : <strong><span id="duree_majoree">0</span> heures</strong>
                                <small class="text-muted d-block">(25% jusqu'à 43h/semaine, 50% au-delà)</small>
                            <?php else: ?>
                                <small class="text-muted d-block">Les heures de récupération réduisent le total d'heures effectives de la semaine</small>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="/index.php" class="btn btn-secondary">Retour</a>
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Calcul automatique de la durée
function calculerDuree() {
    const dateStr = document.getElementById('date').value;
    const debutStr = document.getElementById('heure_debut').value;
    const finStr = document.getElementById('heure_fin').value;
    const typeTemps = '<?php echo $declaration['type_temps']; ?>';
    
    if (dateStr && debutStr && finStr) {
        let debut = new Date(dateStr + 'T' + debutStr);
        let fin = new Date(dateStr + 'T' + finStr);
        
        // Si l'heure de fin est avant l'heure de début, ajouter 24h
        if (fin < debut) {
            fin.setDate(fin.getDate() + 1);
        }
        
        // Calcul de la durée en heures
        let duree = (fin - debut) / (1000 * 60 * 60);
        
        // Arrondir à 2 décimales
        duree = Math.round(duree * 100) / 100;
        
        // Convertir en format heures et minutes pour l'affichage
        let heures = Math.floor(duree);
        let minutes = Math.round((duree - heures) * 60);
        
        // Affichage différent selon le type de temps
        if (typeTemps === 'recuperation') {
            document.getElementById('duree_calculee').innerHTML = 
                `Durée calculée : <strong>${heures}h${minutes > 0 ? minutes : '00'}</strong> (${duree.toString().replace('.', ',')} heures)<br>` +
                `<small class="text-muted d-block">Les heures de récupération réduisent le total d'heures effectives de la semaine</small>`;
        } else {
            // Calcul de la majoration (par défaut 25%)
            let majoration = duree * 0.25; // 25% de majoration standard
            let dureeMajoree = duree + majoration;
            
            // Arrondir la durée majorée à 2 décimales
            dureeMajoree = Math.round(dureeMajoree * 100) / 100;
            
            // Convertir la durée majorée en heures et minutes
            let heuresMajorees = Math.floor(dureeMajoree);
            let minutesMajorees = Math.round((dureeMajoree - heuresMajorees) * 60);
            
            document.getElementById('duree_calculee').innerHTML = 
                `Durée calculée : <strong>${heures}h${minutes > 0 ? minutes : '00'}</strong> (${duree.toString().replace('.', ',')} heures)<br>` +
                `Avec majoration : <strong>${heuresMajorees}h${minutesMajorees > 0 ? minutesMajorees : '00'}</strong> (${dureeMajoree.toString().replace('.', ',')} heures)` +
                `<small class="text-muted d-block">(25% jusqu'à 43h/semaine, 50% au-delà, après déduction des récupérations)</small>`;
        }
    }
}

// Écouter les changements sur les champs
document.getElementById('date').addEventListener('input', calculerDuree);
document.getElementById('heure_debut').addEventListener('input', calculerDuree);
document.getElementById('heure_fin').addEventListener('input', calculerDuree);

// Calculer la durée initiale
calculerDuree();

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