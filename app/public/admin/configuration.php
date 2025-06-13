<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))));
define('APP_PATH', ROOT_PATH . '/app');

session_start();
require_once APP_PATH . '/config/database.php';
require_once APP_PATH . '/models/Client.php';
require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/helpers/auth_helper.php';

// Vérification des permissions
checkPageAccess('configuration');

$db = (new Database())->getConnection();
$client = new Client($db);

// Récupération des permissions actuelles
$roles = ['admin', 'administratif', 'manager', 'user'];
$pages = ['statistiques', 'configuration', 'users', 'validation', 'historique'];

// Initialisation des permissions par défaut si nécessaire
foreach ($roles as $role) {
    foreach ($pages as $page) {
        $check_query = "SELECT COUNT(*) as count FROM role_permissions WHERE role = :role AND page_code = :page_code";
        $stmt = $db->prepare($check_query);
        $stmt->execute([':role' => $role, ':page_code' => $page]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if (!$exists) {
            // Valeur par défaut : true pour admin, false pour les autres
            $default_access = ($role === 'admin') ? 1 : 0;
            $insert_query = "INSERT INTO role_permissions (role, page_code, has_access) VALUES (:role, :page_code, :has_access)";
            $stmt = $db->prepare($insert_query);
            $stmt->execute([
                ':role' => $role,
                ':page_code' => $page,
                ':has_access' => $default_access
            ]);
        }
    }
}

// Récupération des permissions après initialisation
$query = "SELECT role, page_code, has_access FROM role_permissions ORDER BY role, page_code";
$stmt = $db->prepare($query);
$stmt->execute();
$permissions = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $permissions[$row['role']][$row['page_code']] = $row['has_access'];
}

// Traitement du formulaire d'ajout de client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_client':
            $client_data = [
                'nom' => $_POST['nom'],
                'code' => $_POST['code'],
                'adresse' => $_POST['adresse']
            ];
            
            if ($client->create($client_data)) {
                $_SESSION['success'] = "Client ajouté avec succès.";
            } else {
                $_SESSION['error'] = "Erreur lors de l'ajout du client.";
            }
            break;

        case 'update_client':
            if (isset($_POST['client_id'])) {
                $client_data = [
                    'nom' => $_POST['nom'],
                    'code' => $_POST['code'],
                    'adresse' => $_POST['adresse']
                ];
                
                if ($client->update($_POST['client_id'], $client_data)) {
                    $_SESSION['success'] = "Client mis à jour avec succès.";
                } else {
                    $_SESSION['error'] = "Erreur lors de la mise à jour du client.";
                }
            }
            break;

        case 'delete_client':
            if (isset($_POST['client_id'])) {
                if ($client->delete($_POST['client_id'])) {
                    $_SESSION['success'] = "Client supprimé avec succès.";
                } else {
                    $_SESSION['error'] = "Erreur lors de la suppression du client.";
                }
            }
            break;

        case 'update_params':
            try {
                // Préparation des requêtes
                $check_stmt = $db->prepare("SELECT COUNT(*) as count FROM parametres_temps WHERE code = :code");
                $insert_stmt = $db->prepare("INSERT INTO parametres_temps (code, valeur) VALUES (:code, :valeur)");
                $update_stmt = $db->prepare("UPDATE parametres_temps SET valeur = :valeur WHERE code = :code");
                
                $params_to_update = [
                    'entite_mere',
                    'heures_jour_standard',
                    'temps_pause_standard',
                    'debut_journee_standard',
                    'fin_journee_standard',
                    'seuil_declenchement_heures_supp',
                    'heures_semaine_contractuelle',
                    'seuil_majoration_heures_supp',
                    'taux_majoration_standard',
                    'taux_majoration_superieur'
                ];

                $success = true;
                $errors = [];

                foreach ($params_to_update as $code) {
                    if (isset($_POST[$code])) {
                        // Vérifier si le paramètre existe
                        $check_stmt->execute([':code' => $code]);
                        $exists = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                        
                        // Préparer la valeur
                        $valeur = $_POST[$code];
                        
                        // Validation spécifique pour entite_mere
                        if ($code === 'entite_mere' && !is_numeric($valeur)) {
                            $errors[] = "L'ID de la société principale doit être un nombre";
                            continue;
                        }
                        
                        // Exécuter la requête appropriée
                        $stmt = $exists ? $update_stmt : $insert_stmt;
                        $result = $stmt->execute([
                            ':code' => $code,
                            ':valeur' => $valeur
                        ]);
                        
                        if (!$result) {
                            $success = false;
                            $errors[] = "Erreur lors de la mise à jour du paramètre $code : " . 
                                       implode(", ", $stmt->errorInfo());
                        }
                    }
                }
                
                if ($success) {
                    $_SESSION['success'] = "Les paramètres ont été mis à jour avec succès.";
                } else {
                    $_SESSION['error'] = "Erreur lors de la mise à jour des paramètres : " . implode(", ", $errors);
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Erreur lors de la mise à jour : " . $e->getMessage();
            }
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;

        case 'update_permissions':
            // Traitement des permissions
            if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                $success = true;
                
                // Log pour déboguer
                error_log("Début de la mise à jour des permissions");
                error_log("Permissions reçues : " . print_r($_POST['permissions'], true));

                // Préparation des requêtes
                $check_query = "SELECT COUNT(*) as count FROM role_permissions WHERE role = :role AND page_code = :page_code";
                $insert_query = "INSERT INTO role_permissions (role, page_code, has_access) VALUES (:role, :page_code, :has_access)";
                $update_query = "UPDATE role_permissions SET has_access = :has_access WHERE role = :role AND page_code = :page_code";

                $check_stmt = $db->prepare($check_query);
                $insert_stmt = $db->prepare($insert_query);
                $update_stmt = $db->prepare($update_query);

                $roles = ['admin', 'administratif', 'manager', 'user'];
                $pages = ['statistiques', 'configuration', 'users', 'validation', 'historique'];

                foreach ($roles as $role) {
                    foreach ($pages as $page) {
                        // Log pour chaque permission
                        error_log("Traitement de la permission : rôle=$role, page=$page");
                        
                        // Vérifier si la permission existe
                        $check_stmt->execute([
                            ':role' => $role,
                            ':page_code' => $page
                        ]);
                        $exists = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
                        error_log("La permission existe déjà ? " . ($exists ? "Oui" : "Non"));

                        // Définir la valeur d'accès (0 si non cochée, 1 si cochée)
                        $has_access = isset($_POST['permissions'][$role][$page]) ? 1 : 0;
                        error_log("Valeur d'accès définie : $has_access");

                        // Insérer ou mettre à jour selon le cas
                        $stmt = $exists ? $update_stmt : $insert_stmt;
                        $params = [
                            ':role' => $role,
                            ':page_code' => $page,
                            ':has_access' => $has_access
                        ];
                        error_log("Exécution de la requête : " . ($exists ? "UPDATE" : "INSERT"));
                        error_log("Paramètres : " . print_r($params, true));
                        
                        if (!$stmt->execute($params)) {
                            error_log("Erreur lors de l'exécution de la requête : " . print_r($stmt->errorInfo(), true));
                            $success = false;
                            break 2;
                        }
                    }
                }

                if ($success) {
                    $_SESSION['success'] = "Permissions mises à jour avec succès.";
                    error_log("Mise à jour des permissions terminée avec succès");
                } else {
                    $_SESSION['error'] = "Erreur lors de la mise à jour des permissions.";
                    error_log("Erreur lors de la mise à jour des permissions");
                }
            }
            break;
    }

    // Redirection vers la même page pour éviter la soumission multiple du formulaire
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Récupération des paramètres actuels
$query = "SELECT code, valeur FROM parametres_temps";
$stmt = $db->prepare($query);
$stmt->execute();
$params = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $params[$row['code']] = $row['valeur'];
}

// Récupération des clients
$clients = $client->getAll();

// Configuration de l'application
$config = [
    'base_url' => '/app/public',
    'heures_semaine_contractuelle' => 39,
    'seuil_majoration_25' => 8,
    'seuil_majoration_50' => 8
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration - Gestion des Heures Supplémentaires</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --msi-primary: #005E62;
            --msi-secondary: #6AC2D2;
        }
        .navbar-brand, .nav-link, .card-header {
            color: var(--msi-primary) !important;
        }
        .btn-primary {
            background-color: var(--msi-primary) !important;
            border-color: var(--msi-primary) !important;
            color: white !important;
        }
        .btn-primary:hover {
            background-color: var(--msi-secondary) !important;
            border-color: var(--msi-secondary) !important;
            color: var(--msi-primary) !important;
        }
        .btn-outline-primary {
            color: var(--msi-primary) !important;
            border-color: var(--msi-primary) !important;
        }
        .btn-outline-primary:hover {
            background-color: var(--msi-primary) !important;
            border-color: var(--msi-primary) !important;
            color: white !important;
        }
        .btn-outline-danger {
            color: #dc3545 !important;
            border-color: #dc3545 !important;
        }
        .btn-outline-danger:hover {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: white !important;
        }
        .status-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 15px 25px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            display: none;
        }
        .status-message.success {
            background-color: #28a745;
        }
        .status-message.error {
            background-color: #dc3545;
        }
        .form-check-input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .form-check-input:disabled + .form-check-label {
            color: #6c757d;
        }
        .clients-container {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 20px;
        }
        .client-search {
            margin-bottom: 15px;
            position: sticky;
            top: 0;
            background-color: white;
            padding: 10px 0;
            z-index: 1000;
        }
        .client-form {
            background-color: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }
        .client-form .row {
            margin: 0;
        }
        .client-item {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .client-item:last-child {
            border-bottom: none;
        }
        .client-item:hover {
            background-color: #f8f9fa;
        }
        .client-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .client-name {
            font-weight: 500;
            color: var(--msi-primary);
        }
        .client-number {
            color: #6c757d;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <?php include APP_PATH . '/views/layouts/header.php'; ?>

    <div class="container mt-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Gestion des Permissions -->
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Gestion des Permissions</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_permissions">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Rôle</th>
                                            <th>Statistiques</th>
                                            <th>Configuration</th>
                                            <th>Utilisateurs</th>
                                            <th>Validation</th>
                                            <th>Historique</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $roles = ['admin', 'administratif', 'manager', 'user'];
                                        $pages = ['statistiques', 'configuration', 'users', 'validation', 'historique'];
                                        
                                        // Récupération des permissions actuelles
                                        $query = "SELECT role, page_code, has_access FROM role_permissions ORDER BY role, page_code";
                                        $stmt = $db->prepare($query);
                                        $stmt->execute();
                                        $current_permissions = [];
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $current_permissions[$row['role']][$row['page_code']] = $row['has_access'];
                                        }
                                        
                                        foreach ($roles as $role): ?>
                                            <tr>
                                                <td><?php echo ucfirst($role); ?></td>
                                                <?php foreach ($pages as $page): ?>
                                                    <td>
                                                        <div class="form-check">
                                                            <input type="checkbox" 
                                                                class="form-check-input permission-checkbox"
                                                                data-role="<?php echo htmlspecialchars($role); ?>"
                                                                data-page="<?php echo htmlspecialchars($page); ?>"
                                                                <?php echo isset($current_permissions[$role][$page]) && $current_permissions[$role][$page] == 1 ? 'checked' : ''; ?>
                                                                <?php echo $role === 'admin' ? 'disabled' : ''; ?>>
                                                        </div>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div id="permissions-status" class="alert" style="display: none;"></div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Gestion des Clients -->
            <div class="col-md-6 mb-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Gestion des Clients</h3>
                    </div>
                    <div class="card-body">
                        <!-- Formulaire d'ajout de client -->
                        <div class="client-form">
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="add_client">
                                <div class="col-md-6">
                                    <label for="nom" class="form-label">Nom du client</label>
                                    <input type="text" class="form-control" id="nom" name="nom" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="code" class="form-label">Numéro client</label>
                                    <input type="text" class="form-control" id="code" name="code" required>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Ajouter le client
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Recherche de client -->
                        <div class="client-search">
                            <input type="text" id="clientSearch" placeholder="Rechercher un client..." class="form-control">
                        </div>

                        <!-- Liste des clients -->
                        <div class="clients-container">
                            <div id="clientsList">
                                <?php foreach ($clients as $client): ?>
                                    <div class="client-item">
                                        <div class="client-info">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="client_<?php echo $client['id']; ?>" 
                                                    <?php echo (isset($client['actif']) && $client['actif'] == 1) ? 'checked' : ''; ?>>
                                            </div>
                                            <div>
                                                <span class="client-name"><?php echo htmlspecialchars($client['nom']); ?></span>
                                                <span class="client-number">(<?php echo htmlspecialchars($client['code']); ?>)</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paramètres du Système -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Paramètres du Système</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="update_params">
                            
                            <!-- Informations de la société -->
                            <div class="col-12 mb-4">
                                <h6 class="border-bottom pb-2">Informations de la société</h6>
                                <div class="col-md-6 mb-3">
                                    <label for="entite_mere" class="form-label">Société principale</label>
                                    <select class="form-select" id="entite_mere" name="entite_mere">
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['id']; ?>" 
                                                <?php echo (isset($params['entite_mere']) && $params['entite_mere'] == $client['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($client['nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Paramètres contractuels -->
                            <div class="col-12 mb-4">
                                <h6 class="border-bottom pb-2">Paramètres contractuels</h6>
                                <div class="col-md-6 mb-3">
                                    <label for="heures_semaine_contractuelle" class="form-label">Heures contractuelles par semaine</label>
                                    <input type="number" step="0.01" class="form-control" id="heures_semaine_contractuelle" 
                                           name="heures_semaine_contractuelle" 
                                           value="<?php echo htmlspecialchars($params['heures_semaine_contractuelle'] ?? '35.00'); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="heures_jour_standard" class="form-label">Heures standard par jour</label>
                                    <input type="number" step="0.01" class="form-control" id="heures_jour_standard" 
                                           name="heures_jour_standard" 
                                           value="<?php echo htmlspecialchars($params['heures_jour_standard'] ?? '7.00'); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="temps_pause_standard" class="form-label">Temps de pause standard (heures)</label>
                                    <input type="number" step="0.01" class="form-control" id="temps_pause_standard" 
                                           name="temps_pause_standard" 
                                           value="<?php echo htmlspecialchars($params['temps_pause_standard'] ?? '1.00'); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="debut_journee_standard" class="form-label">Heure de début standard</label>
                                    <input type="time" class="form-control" id="debut_journee_standard" 
                                           name="debut_journee_standard" 
                                           value="<?php echo htmlspecialchars($params['debut_journee_standard'] ?? '08:00'); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="fin_journee_standard" class="form-label">Heure de fin standard</label>
                                    <input type="time" class="form-control" id="fin_journee_standard" 
                                           name="fin_journee_standard" 
                                           value="<?php echo htmlspecialchars($params['fin_journee_standard'] ?? '17:00'); ?>">
                                </div>
                            </div>

                            <!-- Gestion des heures supplémentaires -->
                            <div class="col-12 mb-4">
                                <h6 class="border-bottom pb-2">Gestion des heures supplémentaires</h6>
                                <div class="col-md-6 mb-3">
                                    <label for="seuil_declenchement_heures_supp" class="form-label">Seuil minimal pour déclencher des heures supplémentaires</label>
                                    <input type="number" step="0.01" class="form-control" id="seuil_declenchement_heures_supp" 
                                           name="seuil_declenchement_heures_supp" 
                                           value="<?php echo htmlspecialchars($params['seuil_declenchement_heures_supp'] ?? '0.25'); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="seuil_majoration_heures_supp" class="form-label">Seuil pour la majoration des heures supplémentaires</label>
                                    <input type="number" step="0.01" class="form-control" id="seuil_majoration_heures_supp" 
                                           name="seuil_majoration_heures_supp" 
                                           value="<?php echo htmlspecialchars($params['seuil_majoration_heures_supp'] ?? '43.00'); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="taux_majoration_standard" class="form-label">Taux de majoration standard (%)</label>
                                    <input type="number" step="0.01" class="form-control" id="taux_majoration_standard" 
                                           name="taux_majoration_standard" 
                                           value="<?php echo htmlspecialchars($params['taux_majoration_standard'] ?? '25.00'); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="taux_majoration_superieur" class="form-label">Taux de majoration au-delà du seuil (%)</label>
                                    <input type="number" step="0.01" class="form-control" id="taux_majoration_superieur" 
                                           name="taux_majoration_superieur" 
                                           value="<?php echo htmlspecialchars($params['taux_majoration_superieur'] ?? '50.00'); ?>">
                                </div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Enregistrer les paramètres</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de modification de client -->
    <div class="modal fade" id="editClientModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editClientForm" method="POST">
                        <input type="hidden" name="action" value="update_client">
                        <input type="hidden" name="client_id" id="edit_client_id">
                        <div class="mb-3">
                            <label for="edit_nom" class="form-label">Nom du client</label>
                            <input type="text" class="form-control" id="edit_nom" name="nom" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_code" class="form-label">Code client</label>
                            <input type="text" class="form-control" id="edit_code" name="code" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" id="edit_adresse" name="adresse" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" form="editClientForm" class="btn btn-primary">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteClientModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Êtes-vous sûr de vouloir supprimer ce client ?
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete_client">
                        <input type="hidden" name="client_id" id="delete_client_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion des permissions en AJAX
        const checkboxes = document.querySelectorAll('.permission-checkbox');
        const statusDiv = document.getElementById('permissions-status');

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const role = this.dataset.role;
                const page = this.dataset.page;
                const hasAccess = this.checked ? 1 : 0;

                // Envoi de la requête AJAX
                fetch('/admin/update_permission.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        role: role,
                        page_code: page,
                        has_access: hasAccess
                    })
                })
                .then(response => response.json())
                .then(data => {
                    statusDiv.textContent = data.message;
                    statusDiv.className = 'alert alert-' + (data.success ? 'success' : 'danger');
                    statusDiv.style.display = 'block';
                    
                    setTimeout(() => {
                        statusDiv.style.display = 'none';
                    }, 3000);
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    statusDiv.textContent = 'Erreur lors de la mise à jour des permissions';
                    statusDiv.className = 'alert alert-danger';
                    statusDiv.style.display = 'block';
                });
            });
        });
    });

    // Filtre des clients
    document.getElementById('clientSearch').addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const clientsList = document.getElementById('clientsList');
        const clients = clientsList.querySelectorAll('.client-item');

        clients.forEach(client => {
            const nom = client.textContent.toLowerCase();
            if (nom.includes(filter)) {
                client.style.display = '';
            } else {
                client.style.display = 'none';
            }
        });
    });

    function editClient(id) {
        document.getElementById('edit_client_id').value = id;
        new bootstrap.Modal(document.getElementById('editClientModal')).show();
    }

    function deleteClient(id) {
        document.getElementById('delete_client_id').value = id;
        new bootstrap.Modal(document.getElementById('deleteClientModal')).show();
    }
    </script>
</body>
</html> 