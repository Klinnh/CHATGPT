<?php
if (!isset($_SESSION)) {
    session_start();
}

$currentUser = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$isAdmin = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
$isManager = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'manager';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/">MSI2000</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if ($currentUser): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/heures/ajout.php">Ajouter des heures</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/heures/validation.php">Validation</a>
                    </li>
                    <?php if ($isAdmin || $isManager): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/statistiques.php">Statistiques</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if ($currentUser): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear-fill"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="/admin/configuration.php">Configuration</a></li>
                                <li><a class="dropdown-item" href="/admin/utilisateurs.php">Utilisateurs</a></li>
                                <li><a class="dropdown-item" href="/admin/historique.php">Historique</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="/logout.php">Déconnexion</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/login.php">Connexion</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 