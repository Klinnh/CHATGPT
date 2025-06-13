<?php
require_once '../../config/parametres.php';
require_once '../../config/database.php';
require_once '../../models/User.php';
require_once '../../models/HeureSupplementaire.php';

// Configuration de la locale en français et de l'encodage
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fr');
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');

// Vérification de la session et des permissions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../helpers/auth_helper.php';
checkPageAccess('historique');

// Connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Récupération des paramètres
$parametres = Parametres::getInstance();
$heuresContractuelles = $parametres->getHeuresContractuelles();
$seuilMajoration25 = $parametres->getSeuilMajoration25();
$seuilMajoration50 = $parametres->getSeuilMajoration50();

// Récupération des paramètres de filtrage
$annee = isset($_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
$semaine = isset($_GET['semaine']) ? (int)$_GET['semaine'] : (int)date('W');
$service = isset($_GET['service']) ? $_GET['service'] : '';

// Récupération de tous les utilisateurs
$user = new User($db);
$users_stmt = $user->read();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupération des services
$query = "SELECT DISTINCT service FROM users WHERE service IS NOT NULL ORDER BY service";
$stmt = $db->prepare($query);
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Inclusion du header
require_once '../../views/layouts/header.php';
?>

<div class="container-fluid mt-4">
    <h1><i class="far fa-clock"></i> Historique des heures supplémentaires</h1>
    
    <div class="row mb-4">
        <div class="col-md-3">
            <label for="annee">Année</label>
            <select class="form-control" id="annee" name="annee">
                <?php
                $annee_courante = date('Y');
                for ($i = $annee_courante - 2; $i <= $annee_courante + 2; $i++) {
                    echo "<option value=\"$i\"" . ($i == $annee_courante ? " selected" : "") . ">$i</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="mois">Mois</label>
            <select class="form-control" id="mois" name="mois">
                <option value="">Tous les mois</option>
                <?php
                $mois = array(
                    1 => 'Janvier', 2 => 'Février', 3 => 'Mars',
                    4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
                    7 => 'Juillet', 8 => 'Août', 9 => 'Septembre',
                    10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
                );
                foreach ($mois as $num => $nom) {
                    echo "<option value=\"$num\"" . ($num == date('n') ? " selected" : "") . ">$nom</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="semaine">Semaine</label>
            <select class="form-control" id="semaine" name="semaine">
                <option value="">Toutes les semaines</option>
                <?php
                for ($i = 1; $i <= 53; $i++) {
                    echo "<option value=\"$i\">Semaine $i</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="user_id">Salarié</label>
            <select class="form-control" id="user_id" name="user_id">
                <option value="">Tous les salariés</option>
                <?php
                // Récupération des utilisateurs actifs
                $query = "SELECT id, nom, prenom FROM users WHERE actif = 1 ORDER BY nom, prenom";
                $stmt = $db->query($query);
                while ($row = $stmt->fetch()) {
                    echo "<option value=\"" . $row['id'] . "\">" . htmlspecialchars($row['nom'] . " " . $row['prenom']) . "</option>";
                }
                ?>
            </select>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-start mb-3">
                <button type="button" class="btn btn-warning btn-lg" id="recalculer-cumuls">
                    <i class="bi bi-arrow-clockwise"></i> Forcer le recalcul des cumuls
                </button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>SALARIÉ</th>
                    <th>SEMAINE</th>
                    <th>JOUR</th>
                    <th>HEURES SUPP.</th>
                    <th>RÉCUPÉRATION</th>
                    <th>NB HEURES SUR LA SEMAINE</th>
                    <th>NB HEURES SUPP.</th>
                    <th>MAJORATION 25%</th>
                    <th>MAJORATION 50%</th>
                    <th>CUMUL</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody id="historique-data">
                <!-- Les données seront chargées ici via AJAX -->
            </tbody>
        </table>
    </div>
</div>

<!-- Inclusion de Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<!-- Inclusion de Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<style>
.tooltip {
    position: absolute;
    z-index: 1070;
    display: block;
    font-size: 0.875rem;
}
</style>

<script>
// Ajout de la fonction showError au début du script
function showError(message) {
    const tbody = document.getElementById('historique-data');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>${message}
                </td>
            </tr>
        `;
    } else {
        console.error('Element historique-data non trouvé');
    }
}

const CONFIG = {
    heuresContractuelles: <?php echo $heuresContractuelles; ?>,
    seuilMajoration25: <?php echo $seuilMajoration25; ?>,
    seuilMajoration50: <?php echo $seuilMajoration50; ?>,
    tauxMajoration25: <?php echo $parametres->getTauxMajoration25(); ?>,
    tauxMajoration50: <?php echo $parametres->getTauxMajoration50(); ?>
};

document.addEventListener('DOMContentLoaded', function() {
    const anneeSelect = document.getElementById('annee');
    const moisSelect = document.getElementById('mois');
    const semaineSelect = document.getElementById('semaine');
    let workedHoursChart, overtimeChart;
    let currentUserId = null;
    let currentView = 'semaine';
    
    // Ajout du style personnalisé pour les boutons
    const style = document.createElement('style');
    style.textContent = `
        .btn-custom-outline {
            border: 1px solid #006D77;
            color: #006D77;
            background-color: transparent;
        }
        .btn-custom-outline:hover, .btn-custom-outline.active {
            background-color: #006D77;
            color: white;
        }
    `;
    document.head.appendChild(style);
    
    // Fonction pour mettre à jour les semaines en fonction du mois et de l'année sélectionnés
    function updateSemaines() {
        const annee = parseInt(anneeSelect.value);
        const mois = parseInt(moisSelect.value);
        
        semaineSelect.innerHTML = '<option value="">Toutes les semaines</option>';
        
        if (mois) {
            const firstDay = new Date(annee, mois - 1, 1);
            const lastDay = new Date(annee, mois, 0);
            
            const firstWeek = getWeekNumber(firstDay);
            const lastWeek = getWeekNumber(lastDay);
            
            for (let week = firstWeek; week <= lastWeek; week++) {
                const option = document.createElement('option');
                option.value = week;
                option.textContent = `Semaine ${week}`;
                semaineSelect.appendChild(option);
            }
        }
        
        // Charger les données après la mise à jour des semaines
        loadData();
    }
    
    // Fonction pour obtenir le numéro de semaine d'une date
    function getWeekNumber(date) {
        const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        const dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }
    
    // Événements pour mettre à jour les semaines et recharger les données
    anneeSelect.addEventListener('change', updateSemaines);
    moisSelect.addEventListener('change', updateSemaines);
    semaineSelect.addEventListener('change', loadData);
    document.getElementById('user_id').addEventListener('change', loadData);
    
    // Fonction pour charger les données avec indicateur de chargement
    async function loadData() {
        try {
            // Afficher un indicateur de chargement dans le tableau
            const tbody = document.getElementById('historique-data');
            tbody.innerHTML = '<tr><td colspan="11" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div></td></tr>';
            
            const annee = anneeSelect.value;
            const mois = moisSelect.value;
            const semaine = semaineSelect.value;
            const userId = document.getElementById('user_id').value;
            
            // Construction de l'URL avec les paramètres
            const params = new URLSearchParams();
            params.append('annee', annee);
            
            if (mois && mois !== '') {
                params.append('mois', mois);
            }
            
            if (semaine && semaine !== '') {
                params.append('semaine', semaine);
            }
            
            if (userId && userId !== '') {
                params.append('user_id', userId);
            }
            
            const url = `historique_data.php?${params.toString()}`;
            console.log('URL de chargement:', url);
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                // Vérifier si data.data est un tableau
                const resultats = Array.isArray(data.data) ? data.data : [];
                
                if (resultats.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="11" class="text-center">
                                Aucune donnée trouvée pour la période sélectionnée
                            </td>
                        </tr>`;
                    return;
                }

                // Grouper les données par utilisateur et par semaine
                const groupedData = resultats.reduce((acc, item) => {
                    const key = `${item.user_id}-${item.semaine}`;
                    if (!acc[key]) {
                        acc[key] = {
                            userId: item.user_id,
                            userName: `${item.nom} ${item.prenom}`,
                            semaine: item.semaine,
                            jours: []
                        };
                    }
                    acc[key].jours.push(item);
                    return acc;
                }, {});

                // Convertir l'objet groupé en tableau
                const groupes = Object.values(groupedData);
                
                // Vider le tableau
                tbody.innerHTML = '';
                
                // Afficher les données groupées
                groupes.forEach(groupe => {
                    // Ajouter l'en-tête du groupe (nom d'utilisateur + semaine)
                    const headerRow = document.createElement('tr');
                    headerRow.classList.add('table-secondary');
                    headerRow.innerHTML = `
                        <td colspan="11">
                            <strong>${groupe.userName} - Semaine ${groupe.semaine}</strong>
                        </td>
                    `;
                    tbody.appendChild(headerRow);

                    // Variables pour les totaux de la semaine
                    let totalHeuresSupp = 0;
                    let totalRecup = 0;
                    let totalHeureSemaine = 0;
                    let heuresAMajorer = 0;
                    let majoration25 = 0;
                    let majoration50 = 0;

                    // Ajouter chaque jour
                    groupe.jours.forEach(jour => {
                        const tr = document.createElement('tr');
                        const formattedDate = new Date(jour.date_jour).toLocaleDateString('fr-FR');
                        
                        // Calculer les totaux
                        const heuresSupp = jour.type_temps === 'heure_supplementaire' ? parseFloat(jour.duree_calculee || 0) : 0;
                        const recup = jour.type_temps === 'recuperation' ? parseFloat(jour.duree_calculee || 0) : 0;
                        
                        totalHeuresSupp += heuresSupp;
                        totalRecup += recup;
                        
                        tr.innerHTML = `
                            <td>${groupe.userName}</td>
                            <td>${jour.semaine}</td>
                            <td>${formattedDate}</td>
                            <td>${heuresSupp.toFixed(2)}</td>
                            <td>${recup.toFixed(2)}</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-custom-outline show-details" 
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="left"
                                        title="Voir les détails de la journée"
                                        onclick="showDetails('${jour.date_jour}', ${jour.user_id})">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    // Calculer les totaux et majorations
                    totalHeureSemaine = CONFIG.heuresContractuelles + totalHeuresSupp - totalRecup;
                    
                    if (totalHeureSemaine > CONFIG.heuresContractuelles) {
                        heuresAMajorer = totalHeureSemaine - CONFIG.heuresContractuelles;
                        
                        if (totalHeureSemaine <= CONFIG.seuilMajoration50) {
                            // Si on ne dépasse pas le seuil 50%, tout est en majoration 25%
                            majoration25 = heuresAMajorer * 0.25;
                            majoration50 = 0;
                        } else {
                            // Si on dépasse le seuil 50%
                            const heuresMaj25 = CONFIG.seuilMajoration50 - CONFIG.heuresContractuelles;
                            majoration25 = heuresMaj25 * 0.25;
                            
                            const heuresMaj50 = totalHeureSemaine - CONFIG.seuilMajoration50;
                            majoration50 = heuresMaj50 * 0.50;
                        }
                    }

                    // Ajouter la ligne de total
                    const totalRow = document.createElement('tr');
                    totalRow.classList.add('table-info');
                    const solde = totalHeuresSupp - totalRecup;
                    totalRow.innerHTML = `
                        <td colspan="3"><strong>Total Semaine ${groupe.semaine}</strong></td>
                        <td><strong>${totalHeuresSupp.toFixed(2)}</strong></td>
                        <td><strong>${totalRecup.toFixed(2)}</strong></td>
                        <td><strong>${totalHeureSemaine.toFixed(2)}</strong></td>
                        <td><strong>${heuresAMajorer.toFixed(2)}</strong></td>
                        <td><strong>${majoration25.toFixed(2)}</strong></td>
                        <td><strong>${majoration50.toFixed(2)}</strong></td>
                        <td><strong class="${solde < 0 ? 'text-danger' : ''}">${solde.toFixed(2)}</strong></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-custom-outline" 
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="left"
                                    title="Voir l'historique des cumuls"
                                    onclick="showHistoriqueDetails(${groupe.userId}, ${groupe.semaine})">
                                <i class="bi bi-clock-history"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(totalRow);
                });

                // Réinitialiser les tooltips
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                    new bootstrap.Tooltip(tooltipTriggerEl);
                });

            } else {
                showError(data.message || "Erreur lors du chargement des données");
            }
        } catch (error) {
            showError("Erreur lors du chargement des données: " + error.message);
        }
    }

    // Événements pour les filtres
    document.getElementById('user_id').addEventListener('change', loadData);

    // Déplacer le gestionnaire d'événements en dehors de updateTable
    document.getElementById('historique-data').addEventListener('click', function(e) {
        const toggleButton = e.target.closest('.show-details');
        if (!toggleButton) return;

        e.preventDefault();
        e.stopPropagation();

        const mainRow = toggleButton.closest('.main-row');
        if (!mainRow) {
            console.error('Ligne principale non trouvée');
            return;
        }

        const detailsRow = mainRow.nextElementSibling;
        if (!detailsRow || !detailsRow.classList.contains('details-row')) {
            console.error('Ligne de détails non trouvée');
            return;
        }

        const button = toggleButton.querySelector('i') || toggleButton;
        const userId = mainRow.getAttribute('data-user-id');
        const date = mainRow.getAttribute('data-date');

        if (!userId || !date) {
            console.error('ID utilisateur ou date non trouvés');
            return;
        }

        try {
            if (detailsRow.style.display === 'none') {
                detailsRow.style.display = 'table-row';
                button.classList.replace('fa-chevron-down', 'fa-chevron-up');
                const detailsBody = detailsRow.querySelector('.details-body');
                if (detailsBody) {
                    loadAndDisplayDetails(userId, date, detailsBody);
                } else {
                    console.error('Conteneur de détails non trouvé');
                }
            } else {
                detailsRow.style.display = 'none';
                button.classList.replace('fa-chevron-up', 'fa-chevron-down');
            }
        } catch (error) {
            console.error('Erreur lors de la gestion du clic:', error);
        }
    });

    function loadAndDisplayDetails(userId, date, container) {
        if (!container) {
            console.error('Conteneur non valide');
            return;
        }

        const annee = anneeSelect.value;
        const mois = moisSelect.value;
        const semaine = semaineSelect.value;
        
        container.innerHTML = `
            <tr>
                <td colspan="11" class="text-center">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </td>
            </tr>
        `;

        // Construction de l'URL avec les paramètres valides
        let url = `historique_details.php?user_id=${encodeURIComponent(userId)}&annee=${encodeURIComponent(annee)}`;
        if (mois && mois !== '') {
            url += `&mois=${encodeURIComponent(mois)}`;
        }
        if (semaine && semaine !== '') {
            url += `&semaine=${encodeURIComponent(semaine)}`;
        }
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        try {
                            const err = JSON.parse(text);
                            throw new Error(err.error || err.message || `Erreur HTTP: ${response.status}`);
                        } catch (e) {
                            throw new Error(`Erreur HTTP: ${response.status}. ${text}`);
                        }
                    });
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Erreur lors du chargement des détails');
                }
                displayInlineDetails(data.details, container);
            })
            .catch(error => {
                console.error('Erreur:', error);
                container.innerHTML = `
                    <tr>
                        <td colspan="11" class="text-center text-danger">
                            ${error.message || 'Erreur lors du chargement des détails'}
                        </td>
                    </tr>
                `;
            });
    }

    function displayInlineDetails(details, container) {
        container.innerHTML = '';

        if (!Array.isArray(details)) {
            container.innerHTML = '<tr><td colspan="11" class="text-center text-danger">Format de données invalide</td></tr>';
            return;
        }

        if (details.length === 0) {
            container.innerHTML = '<tr><td colspan="11" class="text-center">Aucune demande pour cette période</td></tr>';
            return;
        }

        details.forEach(item => {
            if (!item || typeof item !== 'object') {
                console.error('Élément invalide dans les détails:', item);
                return;
            }

            const tr = document.createElement('tr');
            try {
                const date = new Date(item.date);
                const formattedDate = date.toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });

                tr.innerHTML = `
                    <td>${formattedDate}</td>
                    <td>${item.type_temps === 'heure_supplementaire' ? 'Heure supp.' : 'Récupération'}</td>
                    <td>${item.client || '-'}</td>
                    <td>${item.motif || '-'}</td>
                    <td>${parseFloat(item.duree_calculee || 0).toFixed(2)}h</td>
                    <td>
                        <span class="badge ${
                            item.statut === 'validé' ? 'bg-success' : 
                            item.statut === 'refusé' ? 'bg-danger' : 
                            'bg-warning'
                        }">
                            ${item.statut || 'En attente'}
                        </span>
                    </td>
                `;
                container.appendChild(tr);
            } catch (error) {
                console.error('Erreur lors du formatage des données:', error);
                tr.innerHTML = '<td colspan="11" class="text-center text-danger">Erreur de formatage des données</td>';
                container.appendChild(tr);
            }
        });
    }

    // Initialisation des tooltips Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Fonction pour afficher les détails dans le modal
    function showHistoriqueDetails(userId, semaine) {
        const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
        const modalBody = document.getElementById('detailsContent');
        
        modalBody.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Chargement...</span></div></div>';
        
        fetch(`historique_details.php?user_id=${userId}&semaine=${semaine}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalBody.innerHTML = `
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Client</th>
                                        <th>Motif</th>
                                        <th>Durée</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.details.map(detail => `
                                        <tr>
                                            <td>${new Date(detail.date).toLocaleDateString('fr-FR')}</td>
                                            <td>${detail.type_temps === 'heure_supplementaire' ? 'Heure supp.' : 'Récupération'}</td>
                                            <td>${detail.client || '-'}</td>
                                            <td>${detail.motif || '-'}</td>
                                            <td>${parseFloat(detail.duree_calculee).toFixed(2)}h</td>
                                            <td><span class="badge ${detail.statut === 'validé' ? 'bg-success' : detail.statut === 'refusé' ? 'bg-danger' : 'bg-warning'}">${detail.statut}</span></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                } else {
                    modalBody.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des détails</div>';
                }
                modal.show();
            })
            .catch(error => {
                modalBody.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des détails</div>';
                modal.show();
            });
    }

    // Ajout de la fonction pour recalculer les cumuls
    document.getElementById('recalculer-cumuls').addEventListener('click', async function() {
        if (!confirm('Êtes-vous sûr de vouloir recalculer tous les cumuls ? Cette opération peut prendre du temps.')) {
            return;
        }

        try {
            const response = await fetch('recalcul_cumuls.php');
            const data = await response.json();
            
            if (data.success) {
                alert('Recalcul effectué avec succès');
                loadData(); // Recharger les données
            } else {
                alert('Erreur lors du recalcul: ' + data.message);
            }
        } catch (error) {
            alert('Erreur lors du recalcul: ' + error.message);
        }
    });

    // Chargement initial des données
    updateSemaines();
});
</script>

<?php
// Inclusion du footer
require_once '../../views/layouts/footer.php';
?> 