<?php
if (!isset($db)) {
    require_once dirname(dirname(__DIR__)) . '/config/database.php';
    $database = new Database();
    $db = $database->getConnection();
}

// Inclusion des helpers d'authentification
require_once dirname(dirname(__DIR__)) . '/helpers/auth_helper.php';

// Démarrage de la session si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Heures Supplémentaires - MSI2000</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --msi-primary: #0B5345;
            --msi-secondary: #6AC2D2;
        }

        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background-color: white !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            color: var(--msi-primary) !important;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand img {
            height: 40px;
        }

        .nav-link {
            color: var(--msi-primary) !important;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: var(--msi-secondary) !important;
        }

        .nav-link.active {
            color: var(--msi-secondary) !important;
        }

        .btn-primary {
            background-color: var(--msi-primary);
            border-color: var(--msi-primary);
        }

        .btn-primary:hover {
            background-color: var(--msi-secondary);
            border-color: var(--msi-secondary);
        }

        .btn-secondary {
            background-color: var(--msi-secondary);
            border-color: var(--msi-secondary);
        }

        .btn-secondary:hover {
            background-color: var(--msi-primary);
            border-color: var(--msi-primary);
        }

        .footer {
            background-color: white;
            border-top: 1px solid rgba(0,0,0,0.1);
            padding: 1.5rem 0;
            margin-top: auto;
        }

        .footer a {
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: var(--msi-secondary) !important;
        }

        .text-primary {
            color: var(--msi-primary) !important;
        }

        /* Style pour le rond avec les initiales */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--msi-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .user-avatar:hover {
            background-color: var(--msi-secondary);
        }

        /* Style pour le menu déroulant */
        .user-dropdown, .config-dropdown {
            position: relative;
        }

        .user-dropdown-menu, .config-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 4px;
            min-width: 200px;
            z-index: 1000;
        }

        .user-dropdown-menu.show, .config-dropdown-menu.show {
            display: block;
        }

        .user-info {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        .user-info .user-name {
            font-weight: bold;
            color: var(--msi-primary);
        }

        .user-info .user-role {
            font-size: 0.9em;
            color: #666;
        }

        .dropdown-item {
            padding: 0.5rem 1rem;
            color: var(--msi-primary);
            text-decoration: none;
            display: block;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: var(--msi-secondary);
        }

        .config-icon, .stats-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: white;
            color: var(--msi-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.2rem;
            border: 1px solid var(--msi-primary);
            margin-right: 15px;
        }

        .config-icon:hover, .stats-icon:hover {
            background-color: var(--msi-primary);
            color: white;
        }

        /* Styles spécifiques à la page liste */
        .table th {
            background-color: white;
            color: var(--msi-primary);
            border-bottom: 2px solid var(--msi-primary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
            padding: 1rem;
        }
        .table td {
            vertical-align: middle;
        }
        .status-badge {
            padding: 0.5em 1em;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.2rem;
        }
        .btn-edit {
            background-color: var(--msi-primary);
            color: white;
            border: none;
        }
        .btn-edit:hover {
            background-color: var(--msi-secondary);
            color: white;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        .btn-delete:hover {
            background-color: #c82333;
            color: white;
        }
        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: white;
            border-bottom: 2px solid var(--msi-primary);
            color: var(--msi-primary);
            font-weight: 600;
        }
        .table-responsive {
            background-color: white;
            border-radius: 0.25rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .filter-type-switch {
            margin-bottom: 1rem;
        }
        .btn-outline-theme {
            color: #0B5345;
            border: 1px solid #0B5345 !important;
            background-color: white;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .btn-outline-theme:hover {
            background-color: #6AC2D2 !important;
            color: white !important;
            border: 1px solid #6AC2D2 !important;
        }
        .btn-outline-theme.active {
            background-color: #0B5345 !important;
            color: white !important;
            border: 1px solid #0B5345 !important;
        }
        .btn-group .btn-outline-theme {
            margin: 0;
            border-radius: 0;
        }
        .btn-group .btn-outline-theme:first-child {
            border-top-left-radius: 4px;
            border-bottom-left-radius: 4px;
        }
        .btn-group .btn-outline-theme:last-child {
            border-top-right-radius: 4px;
            border-bottom-right-radius: 4px;
        }
        .resume-box {
            background-color: #cce5ff;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .resume-box p {
            margin: 0;
            color: #004085;
        }
        .card.bg-success {
            background-color: #9CDB9A !important;
        }
        .btn-new {
            color: white;
            border: 1px solid #0B5345 !important;
            background-color: #0B5345;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .btn-new:hover {
            background-color: #17A589 !important;
            color: white !important;
            border: 1px solid #0B5345 !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="/">
                <img src="/assets/images/MSI2000 LOGO5.png" alt="MSI2000 Logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php
                        // Récupération des permissions de l'utilisateur
                        $role = $_SESSION['user_role'];
                        $query = "SELECT page_code, has_access FROM role_permissions WHERE role = :role";
                        $stmt = $db->prepare($query);
                        $stmt->execute([':role' => $role]);
                        $permissions = [];
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $permissions[$row['page_code']] = $row['has_access'];
                        }
                        ?>
                        <?php if ($permissions['statistiques'] ?? false): ?>
                            <li class="nav-item">
                                <a href="/admin/statistiques.php" class="stats-icon">
                                    <i class="bi bi-graph-up"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($permissions['configuration'] ?? false): ?>
                            <li class="nav-item config-dropdown">
                                <div class="config-icon" onclick="toggleConfigMenu()">
                                    <i class="bi bi-gear-fill"></i>
                                </div>
                                <div class="config-dropdown-menu" id="configDropdownMenu">
                                    <a href="/admin/configuration.php" class="dropdown-item">
                                        <i class="bi bi-sliders"></i> Configuration
                                    </a>
                                    <a href="/admin/users.php" class="dropdown-item">
                                        <i class="bi bi-people"></i> Utilisateurs
                                    </a>
                                    <?php if ($permissions['historique'] ?? false): ?>
                                    <a href="/admin/historique.php" class="dropdown-item">
                                        <i class="bi bi-clock-history"></i> Historique
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item user-dropdown">
                            <div class="user-avatar" onclick="toggleUserMenu()">
                                <?php
                                    $prenom = $_SESSION['user_prenom'] ?? '';
                                    $nom = $_SESSION['user_nom'] ?? '';
                                    $initials = strtoupper(
                                        ($prenom ? substr($prenom, 0, 1) : '') . 
                                        ($nom ? substr($nom, 0, 1) : '')
                                    );
                                    echo $initials ?: 'U';  // 'U' pour User si pas d'initiales
                                ?>
                            </div>
                            <div class="user-dropdown-menu" id="userDropdownMenu">
                                <div class="user-info">
                                    <div class="user-name">
                                        <?php 
                                            echo htmlspecialchars(
                                                ($prenom ? $prenom . ' ' : '') . 
                                                ($nom ?: 'Utilisateur')
                                            ); 
                                        ?>
                                    </div>
                                    <div class="user-role">
                                        <?php
                                            $roles = [
                                                'admin' => 'Administrateur',
                                                'administratif' => 'Administratif',
                                                'manager' => 'Manager',
                                                'user' => 'Utilisateur'
                                            ];
                                            echo $roles[$_SESSION['user_role']] ?? $_SESSION['user_role'];
                                        ?>
                                    </div>
                                </div>
                                <a href="/logout.php" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                                </a>
                            </div>
                        </li>
                    <?php else: ?>
                        <?php if (basename($_SERVER['PHP_SELF']) !== 'login.php'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/login.php">Connexion</a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleUserMenu() {
            const menu = document.getElementById('userDropdownMenu');
            menu.classList.toggle('show');
        }

        function toggleConfigMenu() {
            const menu = document.getElementById('configDropdownMenu');
            menu.classList.toggle('show');
        }

        // Fermer les menus si on clique en dehors
        document.addEventListener('click', function(event) {
            const userDropdown = document.querySelector('.user-dropdown');
            const userMenu = document.getElementById('userDropdownMenu');
            const configDropdown = document.querySelector('.config-dropdown');
            const configMenu = document.getElementById('configDropdownMenu');

            if (!userDropdown.contains(event.target) && userMenu.classList.contains('show')) {
                userMenu.classList.remove('show');
            }
            if (!configDropdown.contains(event.target) && configMenu.classList.contains('show')) {
                configMenu.classList.remove('show');
            }
        });
    </script>
</body>
</html> 