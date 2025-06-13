<?php
// Vérification AJAX uniquement
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    exit('Accès direct non autorisé');
}

require_once dirname(dirname(dirname(__DIR__))) . '/app/config/database.php';

$db = (new Database())->getConnection();

// Récupération des clients
$query = "SELECT id, nom, code FROM clients WHERE actif = 1 ORDER BY nom";
$stmt = $db->prepare($query);
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération de l'entité mère (MSI2000)
$query = "SELECT valeur FROM parametres_temps WHERE code = 'entite_mere'";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$entite_mere_id = $result ? $result['valeur'] : null;
?>

<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title">Déclarer du temps supplémentaire</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
    </div>
    <div class="modal-body">
        <form id="declarationForm" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="date" class="form-label">Date *</label>
                    <input type="date" class="form-control" id="date" name="date" required>
                    <div class="invalid-feedback">La date est requise</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="type" class="form-label">Type de temps *</label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="heure_supplementaire">Heure supplémentaire</option>
                        <option value="recuperation">Récupération</option>
                    </select>
                    <div class="invalid-feedback">Le type est requis</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="heure_debut" class="form-label">Heure de début *</label>
                    <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                    <div class="invalid-feedback">L'heure de début est requise</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="heure_fin" class="form-label">Heure de fin *</label>
                    <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                    <div class="invalid-feedback">L'heure de fin est requise</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="client_id" class="form-label">Client *</label>
                    <select class="form-select" id="client_id" name="client_id" required>
                        <option value="">Sélectionnez un client</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" 
                                    <?php echo ($client['id'] == $entite_mere_id) ? 'data-is-msi="1"' : ''; ?>>
                                <?php echo htmlspecialchars($client['nom']); ?>
                                <?php if ($client['code']): ?>
                                    (<?php echo htmlspecialchars($client['code']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Le client est requis</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="temps_pause" class="form-label">Temps de pause (h)</label>
                    <input type="number" class="form-control" id="temps_pause" name="temps_pause" 
                           min="0" max="12" step="0.25" value="0">
                    <div class="invalid-feedback">La valeur doit être entre 0 et 12</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="motif" class="form-label">Motif *</label>
                    <select class="form-select" id="motif" name="motif" required>
                        <option value="">Sélectionnez un motif</option>
                        <option value="demande_recuperation">Demande de récupération</option>
                        <option value="surcharge">Surcharge</option>
                        <option value="urgence">Urgence</option>
                        <option value="remplacement">Remplacement</option>
                        <option value="projet">Projet</option>
                        <option value="autre">Autre</option>
                    </select>
                    <div class="invalid-feedback">Le motif est requis</div>
                </div>
            </div>

            <div class="mb-3">
                <label for="commentaire" class="form-label">Commentaire</label>
                <textarea class="form-control" id="commentaire" name="commentaire" rows="3"
                          placeholder="Détails supplémentaires sur l'intervention..."></textarea>
            </div>

            <div class="alert alert-info" id="duree_calculee">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Durée calculée :</strong>
                        <span id="duree_heures">0</span> heures
                    </div>
                    <div class="col-md-6">
                        <strong>Avec majoration :</strong>
                        <span id="duree_majoree">0</span> heures
                    </div>
                </div>
                <small class="text-muted d-block mt-1">
                    (25% jusqu'à 43h/semaine, 50% au-delà)
                </small>
            </div>
        </form>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary" id="submitDeclaration">Enregistrer</button>
    </div>
</div>

<script>
// Fonction pour calculer la durée
function calculerDuree() {
    const dateInput = document.getElementById('date');
    const startInput = document.getElementById('heure_debut');
    const endInput = document.getElementById('heure_fin');
    const pauseInput = document.getElementById('temps_pause');
    const typeSelect = document.getElementById('type');
    
    if (dateInput.value && startInput.value && endInput.value) {
        // Convertir les heures en minutes
        const [startHours, startMinutes] = startInput.value.split(':').map(Number);
        const [endHours, endMinutes] = endInput.value.split(':').map(Number);
        
        let startTotalMinutes = startHours * 60 + startMinutes;
        let endTotalMinutes = endHours * 60 + endMinutes;
        
        // Si l'heure de fin est avant l'heure de début, ajouter 24h
        if (endTotalMinutes < startTotalMinutes) {
            endTotalMinutes += 24 * 60;
        }
        
        // Calculer la durée en heures
        let duree = (endTotalMinutes - startTotalMinutes) / 60;
        duree -= parseFloat(pauseInput.value || 0);
        
        // Arrondir à 2 décimales
        duree = Math.round(duree * 100) / 100;
        
        // Calculer la majoration
        let majoration = duree * 0.25;
        let dureeMajoree = duree + majoration;
        
        // Mettre à jour l'affichage
        document.getElementById('duree_heures').textContent = duree.toFixed(2);
        document.getElementById('duree_majoree').textContent = dureeMajoree.toFixed(2);
    }
}

// Fonction pour mettre à jour les contraintes de date
function updateDateConstraints() {
    const dateInput = document.getElementById('date');
    const typeSelect = document.getElementById('type');
    const today = new Date().toISOString().split('T')[0];

    if (typeSelect.value === 'heure_supplementaire') {
        dateInput.max = today;
        dateInput.min = '';
    } else {
        dateInput.min = today;
        dateInput.max = '';
    }

    if ((typeSelect.value === 'heure_supplementaire' && dateInput.value > today) ||
        (typeSelect.value === 'recuperation' && dateInput.value < today)) {
        dateInput.value = '';
    }
}

// Fonction pour gérer le changement de type
function handleTypeChange() {
    const typeSelect = document.getElementById('type');
    const clientSelect = document.getElementById('client_id');
    const motifSelect = document.getElementById('motif');

    if (typeSelect.value === 'recuperation') {
        clientSelect.value = document.querySelector('option[data-is-msi="1"]').value;
        clientSelect.disabled = true;
        clientSelect.style.backgroundColor = '#e9ecef';
        
        motifSelect.value = 'demande_recuperation';
        motifSelect.disabled = true;
        motifSelect.style.backgroundColor = '#e9ecef';
    } else {
        clientSelect.disabled = false;
        clientSelect.style.backgroundColor = '';
        motifSelect.disabled = false;
        motifSelect.style.backgroundColor = '';
        motifSelect.value = '';
    }
    
    updateDateConstraints();
    calculerDuree();
}

// Fonction pour gérer la soumission
function handleSubmit(event) {
    event.preventDefault();
    
    const form = document.getElementById('declarationForm');
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const formData = new FormData();
    
    // Ajout des champs requis
    const fields = {
        'date': document.getElementById('date').value,
        'type': document.getElementById('type').value,
        'heure_debut': document.getElementById('heure_debut').value,
        'heure_fin': document.getElementById('heure_fin').value,
        'client_id': document.getElementById('client_id').value,
        'motif': document.getElementById('motif').value,
        'temps_pause': document.getElementById('temps_pause').value || '0',
        'commentaire': document.getElementById('commentaire').value || ''
    };

    // Vérification des champs requis
    for (const [key, value] of Object.entries(fields)) {
        if (!value && key !== 'commentaire' && key !== 'temps_pause') {
            alert('Veuillez remplir tous les champs obligatoires');
            return;
        }
        formData.append(key, value);
    }

    // Désactiver le bouton pendant la soumission
    const submitButton = document.getElementById('submitDeclaration');
    submitButton.disabled = true;
    
    fetch('/api/heures/create.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('declarationModal'));
            if (modal) {
                modal.hide();
            }
            window.location.reload();
        } else {
            alert(data.message || 'Une erreur est survenue');
            submitButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Une erreur est survenue lors de l\'enregistrement');
        submitButton.disabled = false;
    });
}

// Attacher les événements immédiatement
document.getElementById('date').addEventListener('input', calculerDuree);
document.getElementById('date').addEventListener('change', calculerDuree);
document.getElementById('heure_debut').addEventListener('input', calculerDuree);
document.getElementById('heure_debut').addEventListener('change', calculerDuree);
document.getElementById('heure_fin').addEventListener('input', calculerDuree);
document.getElementById('heure_fin').addEventListener('change', calculerDuree);
document.getElementById('temps_pause').addEventListener('input', calculerDuree);
document.getElementById('temps_pause').addEventListener('change', calculerDuree);
document.getElementById('type').addEventListener('change', handleTypeChange);

// Attacher l'événement de soumission directement au bouton
document.getElementById('submitDeclaration').onclick = handleSubmit;

// Initialisation de l'état
handleTypeChange();
updateDateConstraints();
</script> 