<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/HeureSupplementaire.php';

session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php?error=not_logged_in');
    exit;
}

$db = (new Database())->getConnection();
$error = '';
$success = '';

// Récupération de l'entité mère (MSI2000)
$query = "SELECT valeur FROM parametres_temps WHERE code = 'entite_mere'";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$entite_mere_id = $result ? $result['valeur'] : null;

// Récupération des clients
$query = "SELECT id, nom, code FROM clients WHERE actif = 1 ORDER BY nom";
$stmt = $db->prepare($query);
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des paramètres
$stmt = $db->query("SELECT code, valeur FROM parametres_temps");
$parametres = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $parametres[$row['code']] = $row['valeur'];
}

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
        
        // Si c'est une récupération, on utilise l'entité mère comme client
        if ($_POST['type'] === 'recuperation') {
            if (!$entite_mere_id) {
                $errors[] = "L'entité mère n'est pas configurée dans les paramètres";
            }
            $_POST['client_id'] = $entite_mere_id;
        } else if (empty($_POST['client_id'])) {
            $errors[] = "Le client est requis";
        }
        
        if (empty($_POST['type'])) {
            $errors[] = "Le type d'heures est requis";
        }
        
        if (empty($_POST['motif'])) {
            $_POST['motif'] = "autre"; // Valeur valide de l'ENUM
        }

        if (empty($errors)) {
            $heure = new HeureSupplementaire($db);
            
            // Calcul de la durée
            $debut = strtotime($_POST['date'] . ' ' . $_POST['heure_debut']);
            $fin = strtotime($_POST['date'] . ' ' . $_POST['heure_fin']);
            
            // Si l'heure de fin est avant l'heure de début, on ajoute 24h
            if ($fin < $debut) {
                $fin += 86400; // 24 heures en secondes
            }
            
            $duree = ($fin - $debut) / 3600; // Conversion en heures
            $duree -= floatval($_POST['temps_pause'] ?? 0);

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
            AND date_jour BETWEEN :debut_semaine AND :fin_semaine";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':debut_semaine', $debut_semaine);
            $stmt->bindParam(':fin_semaine', $fin_semaine);
            $stmt->execute();
            $heures_semaine = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total_effectif']);

            // Si on ajoute une récupération, pas de majoration
            if ($_POST['type'] === 'recuperation') {
                $majorations = [
                    'montants' => ['standard' => 0, 'superieur' => 0],
                    'taux_majoration_standard' => 0,
                    'taux_majoration_superieur' => 0
                ];
            } else {
                // Pour les heures supplémentaires, on calcule les majorations sur le total effectif
                $majorations = $heure->calculateMajorations($duree, $heures_semaine);
            }

            $data = [
                'user_id' => $_SESSION['user_id'],
                'date_jour' => $_POST['date'],
                'client_id' => $_POST['client_id'],
                'heure_debut' => $_POST['heure_debut'],
                'heure_fin' => $_POST['heure_fin'],
                'duree_calculee' => $duree,
                'type_temps' => $_POST['type'],
                'motif' => $_POST['motif'],
                'temps_pause' => 0,
                'commentaire' => $_POST['commentaire'] ?? null,
                'statut' => 'en_attente',
                'majoration_standard' => $majorations['montants']['standard'],
                'majoration_superieur' => $majorations['montants']['superieur'],
                'taux_majoration_standard' => $majorations['taux_majoration_standard'],
                'taux_majoration_superieur' => $majorations['taux_majoration_superieur']
            ];

            if ($heure->create($data)) {
                $_SESSION['success'] = "Les heures ont été enregistrées avec succès.";
                header('Location: /index.php');
                exit;
            } else {
                $error = "Une erreur est survenue lors de l'enregistrement.";
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
                    <h2>Déclarer du temps supplémentaire</h2>
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
                                <label for="date" class="form-label">Date *</label>
                                <input type="date" class="form-control" id="date" name="date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Type de temps *</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="heure_supplementaire">Heure supplémentaire</option>
                                    <option value="recuperation">Récupération</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="heure_debut" class="form-label">Heure de début *</label>
                                <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="heure_fin" class="form-label">Heure de fin *</label>
                                <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="client_id" class="form-label">Client *</label>
                            <select class="form-select" id="client_id" name="client_id" required>
                                <option value="">Sélectionnez un client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>">
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
                            <select class="form-select" id="motif" name="motif" required>
                                <option value="">Sélectionnez un motif</option>
                                <option value="surcharge">Surcharge</option>
                                <option value="urgence">Urgence</option>
                                <option value="remplacement">Remplacement</option>
                                <option value="projet">Projet</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="commentaire" class="form-label">Commentaire</label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
                        </div>

                        <div class="alert alert-info" id="duree_calculee" style="display: none;">
                            Durée calculée : <strong><span id="duree_heures">0</span> heures</strong><br>
                            Avec majoration : <strong><span id="duree_majoree">0</span> heures</strong>
                            <small class="text-muted d-block">(25% jusqu'à 43h/semaine, 50% au-delà)</small>
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
    const typeTemps = document.getElementById('type').value;
    
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
        
        document.getElementById('duree_calculee').style.display = 'block';
    }
}

// Écouter les changements sur les champs
document.getElementById('date').addEventListener('change', calculerDuree);
document.getElementById('heure_debut').addEventListener('change', calculerDuree);
document.getElementById('heure_fin').addEventListener('change', calculerDuree);
document.getElementById('type').addEventListener('change', calculerDuree);

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

document.addEventListener('DOMContentLoaded', function() {
    const typeTempsSelect = document.getElementById('type');
    const clientSelect = document.getElementById('client_id');
    const motifSelect = document.getElementById('motif');
    
    // Récupérer l'ID de l'entité mère depuis les paramètres
    const entiteMereId = '<?php echo $entite_mere_id; ?>';
    
    typeTempsSelect.addEventListener('change', function() {
        if (this.value === 'recuperation') {
            // Si c'est une récupération
            if (entiteMereId) {
                clientSelect.value = entiteMereId;
            }
            clientSelect.disabled = true;
            
            // Forcer le motif à "autre" pour les récupérations
            if (!motifSelect.querySelector('option[value="autre"]')) {
                const option = new Option('Récupération', 'autre');
                motifSelect.add(option);
            }
            motifSelect.value = 'autre';
            motifSelect.disabled = true;
            
            // Mettre à jour l'affichage de la durée
            calculerDuree();
        } else {
            // Si ce n'est pas une récupération
            clientSelect.disabled = false;
            motifSelect.disabled = false;
            
            // Retirer l'option de récupération si elle existe
            const recupOption = motifSelect.querySelector('option[value="autre"]');
            if (recupOption) {
                recupOption.remove();
            }
            
            // Mettre à jour l'affichage de la durée
            calculerDuree();
        }
    });
});
</script>

<?php require_once APP_PATH . '/views/layouts/footer.php'; ?> 