<?php
header('Content-Type: application/json');

// Vérification de la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Vérification de l'authentification
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/app/config/database.php';

try {
    $db = (new Database())->getConnection();
    
    // Validation des données requises
    $required_fields = ['date', 'type', 'heure_debut', 'heure_fin', 'client_id', 'motif'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception('Champs requis manquants : ' . implode(', ', $missing_fields));
    }

    // Validation du type
    if (!in_array($_POST['type'], ['heure_supplementaire', 'recuperation'])) {
        throw new Exception('Type de temps invalide');
    }

    // Validation du motif
    if (!in_array($_POST['motif'], ['surcharge', 'urgence', 'remplacement', 'projet', 'autre', 'demande_recuperation'])) {
        throw new Exception('Motif invalide');
    }

    // Validation de la date
    $date = new DateTime($_POST['date']);
    $today = new DateTime();
    
    if ($_POST['type'] === 'heure_supplementaire' && $date > $today) {
        throw new Exception('La date des heures supplémentaires ne peut pas être dans le futur');
    }
    
    if ($_POST['type'] === 'recuperation' && $date < $today) {
        throw new Exception('La date de récupération ne peut pas être dans le passé');
    }

    // Préparation des données
    $data = [
        'user_id' => $_SESSION['user_id'],
        'client_id' => $_POST['client_id'],
        'date_jour' => $_POST['date'],
        'heure_debut' => $_POST['heure_debut'],
        'heure_fin' => $_POST['heure_fin'],
        'temps_pause' => isset($_POST['temps_pause']) ? floatval($_POST['temps_pause']) : 0,
        'type_temps' => $_POST['type'],
        'motif' => $_POST['motif'],
        'commentaire' => isset($_POST['commentaire']) ? $_POST['commentaire'] : null,
        'statut' => 'en_attente'
    ];

    // Insertion dans la base de données
    $sql = "INSERT INTO declarations (
        user_id, client_id, date_jour, heure_debut, heure_fin, 
        temps_pause, type_temps, motif, commentaire, statut
    ) VALUES (
        :user_id, :client_id, :date_jour, :heure_debut, :heure_fin,
        :temps_pause, :type_temps, :motif, :commentaire, :statut
    )";

    $stmt = $db->prepare($sql);
    
    if ($stmt->execute($data)) {
        echo json_encode([
            'success' => true,
            'message' => 'Déclaration enregistrée avec succès',
            'id' => $db->lastInsertId()
        ]);
    } else {
        throw new Exception('Erreur lors de l\'enregistrement');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 