<?php
require_once 'BaseModel.php';

class HeureSupplementaire extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table_name = 'heures_supplementaires';
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                (user_id, client_id, date_jour, heure_debut, heure_fin, duree_calculee, 
                type_temps, motif, temps_pause, statut, commentaire, 
                majoration_standard, majoration_superieur, 
                taux_majoration_standard, taux_majoration_superieur, created_at) 
                VALUES 
                (:user_id, :client_id, :date_jour, :heure_debut, :heure_fin, :duree_calculee, 
                :type_temps, :motif, :temps_pause, :statut, :commentaire,
                :majoration_standard, :majoration_superieur,
                :taux_majoration_standard, :taux_majoration_superieur, NOW())";

        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':client_id', $data['client_id']);
        $stmt->bindParam(':date_jour', $data['date_jour']);
        $stmt->bindParam(':heure_debut', $data['heure_debut']);
        $stmt->bindParam(':heure_fin', $data['heure_fin']);
        $stmt->bindParam(':duree_calculee', $data['duree_calculee']);
        $stmt->bindParam(':type_temps', $data['type_temps']);
        $stmt->bindParam(':motif', $data['motif']);
        $stmt->bindParam(':temps_pause', $data['temps_pause']);
        $stmt->bindParam(':statut', $data['statut']);
        $stmt->bindParam(':commentaire', $data['commentaire']);
        $stmt->bindParam(':majoration_standard', $data['majoration_standard']);
        $stmt->bindParam(':majoration_superieur', $data['majoration_superieur']);
        $stmt->bindParam(':taux_majoration_standard', $data['taux_majoration_standard']);
        $stmt->bindParam(':taux_majoration_superieur', $data['taux_majoration_superieur']);

        return $stmt->execute();
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . "
                SET date_jour = :date_jour,
                    heure_debut = :heure_debut,
                    heure_fin = :heure_fin,
                    duree_calculee = :duree_calculee,
                    type_temps = :type_temps,
                    motif = :motif,
                    temps_pause = :temps_pause,
                    statut = :statut,
                    commentaire = :commentaire,
                    client_id = :client_id,
                    majoration_standard = :majoration_standard,
                    majoration_superieur = :majoration_superieur,
                    taux_majoration_standard = :taux_majoration_standard,
                    taux_majoration_superieur = :taux_majoration_superieur,
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':date_jour', $data['date_jour']);
        $stmt->bindParam(':heure_debut', $data['heure_debut']);
        $stmt->bindParam(':heure_fin', $data['heure_fin']);
        $stmt->bindParam(':duree_calculee', $data['duree_calculee']);
        $stmt->bindParam(':type_temps', $data['type_temps']);
        $stmt->bindParam(':motif', $data['motif']);
        $stmt->bindParam(':temps_pause', $data['temps_pause']);
        $stmt->bindParam(':statut', $data['statut']);
        $stmt->bindParam(':commentaire', $data['commentaire']);
        $stmt->bindParam(':client_id', $data['client_id']);
        $stmt->bindParam(':majoration_standard', $data['majoration_standard']);
        $stmt->bindParam(':majoration_superieur', $data['majoration_superieur']);
        $stmt->bindParam(':taux_majoration_standard', $data['taux_majoration_standard']);
        $stmt->bindParam(':taux_majoration_superieur', $data['taux_majoration_superieur']);

        return $stmt->execute();
    }

    public function getUserHours($user_id, $month = null, $year = null) {
        $query = "SELECT hs.*, u.nom, u.prenom, c.nom as client_nom,
                CASE 
                    WHEN hs.type_temps = 'recuperation' THEN 'récupération'
                    ELSE 'heure_supplementaire'
                END as type
                FROM " . $this->table_name . " hs
                LEFT JOIN users u ON hs.user_id = u.id
                LEFT JOIN clients c ON hs.client_id = c.id
                WHERE hs.user_id = :user_id";

        if ($month && $year) {
            $query .= " AND MONTH(hs.date_jour) = :month AND YEAR(hs.date_jour) = :year";
        }

        $query .= " ORDER BY hs.date_jour DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($month && $year) {
            $stmt->bindParam(':month', $month);
            $stmt->bindParam(':year', $year);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function calculateMonthlyTotal($user_id, $month, $year) {
        $query = "SELECT SUM(duree_calculee) as total 
                 FROM " . $this->table_name . "
                 WHERE user_id = :user_id 
                 AND MONTH(date_jour) = :month 
                 AND YEAR(date_jour) = :year
                 AND type_temps = 'heure_supplementaire'
                 AND statut = 'validé'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':month', $month);
        $stmt->bindParam(':year', $year);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    public function getPendingValidations($manager_id) {
        // Récupérer le rôle et le service de l'utilisateur
        $query_role = "SELECT role, service FROM users WHERE id = :manager_id";
        $stmt_role = $this->conn->prepare($query_role);
        $stmt_role->bindParam(':manager_id', $manager_id);
        $stmt_role->execute();
        $user_info = $stmt_role->fetch(PDO::FETCH_ASSOC);
        $user_role = $user_info['role'];
        $user_service = $user_info['service'];

        // Si l'utilisateur est admin, il voit toutes les demandes
        if ($user_role === 'admin') {
            $query = "SELECT hs.*, u.nom, u.prenom, u.service, c.nom as client_nom
                     FROM heures_supplementaires hs
                     LEFT JOIN users u ON hs.user_id = u.id
                     LEFT JOIN clients c ON hs.client_id = c.id
                     WHERE hs.statut = 'en_attente'
                     ORDER BY hs.date_jour DESC, hs.heure_debut DESC";
        } else {
            // Sinon, il ne voit que les demandes de son service
            $query = "SELECT hs.*, u.nom, u.prenom, u.service, c.nom as client_nom
                     FROM heures_supplementaires hs
                     LEFT JOIN users u ON hs.user_id = u.id
                     LEFT JOIN clients c ON hs.client_id = c.id
                     WHERE hs.statut = 'en_attente'
                     AND u.service = :service
                     ORDER BY hs.date_jour DESC, hs.heure_debut DESC";
        }

        $stmt = $this->conn->prepare($query);
        if ($user_role !== 'admin') {
            $stmt->bindParam(':service', $user_service);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status, $commentaire = null, $majorations = null) {
        $query = "UPDATE " . $this->table_name . "
                SET statut = :status, 
                    commentaire = CASE 
                        WHEN :commentaire IS NOT NULL THEN :commentaire 
                        ELSE commentaire 
                    END,";

        // Ajout des champs de majoration si fournis
        if ($majorations !== null) {
            $query .= "
                    majoration_standard = :majoration_standard,
                    majoration_superieur = :majoration_superieur,
                    taux_majoration_standard = :taux_standard,
                    taux_majoration_superieur = :taux_superieur,";
        }

        $query .= "
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        
        // Paramètres de base
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':commentaire', $commentaire);

        // Paramètres de majoration si fournis
        if ($majorations !== null) {
            $stmt->bindParam(':majoration_standard', $majorations['montants']['standard']);
            $stmt->bindParam(':majoration_superieur', $majorations['montants']['superieur']);
            $stmt->bindParam(':taux_standard', $majorations['taux_majoration_standard']);
            $stmt->bindParam(':taux_superieur', $majorations['taux_majoration_superieur']);
        }

        return $stmt->execute();
    }

    // Méthode pour lire les heures supplémentaires d'un utilisateur
    public function readByUser($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = ? ORDER BY date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        return $stmt;
    }

    // Méthode pour lire les heures supplémentaires par période
    public function readByPeriod($start_date, $end_date) {
        $query = "SELECT * FROM " . $this->table_name . " 
                 WHERE date BETWEEN ? AND ? 
                 ORDER BY date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $start_date);
        $stmt->bindParam(2, $end_date);
        $stmt->execute();
        return $stmt;
    }

    // Méthode pour valider une heure supplémentaire
    public function validate($id, $validated_by) {
        $query = "UPDATE " . $this->table_name . " 
                 SET status = 'validé', 
                     validated_by = ?, 
                     validated_at = NOW() 
                 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$validated_by, $id]);
    }

    // Méthode pour rejeter une heure supplémentaire
    public function reject($id, $rejected_by, $reason) {
        $query = "UPDATE " . $this->table_name . " 
                 SET status = 'rejeté', 
                     rejected_by = ?, 
                     rejected_at = NOW(),
                     rejection_reason = ? 
                 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$rejected_by, $reason, $id]);
    }

    public function calculateMajorations($duree, $heures_semaine) {
        // Récupération des paramètres de configuration
        $query = "SELECT code, valeur FROM parametres_temps WHERE code IN ('seuil_majoration_heures_supp', 'taux_majoration_standard', 'taux_majoration_superieur')";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $params = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $params[$row['code']] = floatval($row['valeur']);
        }

        // Valeurs par défaut si les paramètres ne sont pas trouvés
        $seuil_majoration = $params['seuil_majoration_heures_supp'] ?? 43;
        $taux_standard = ($params['taux_majoration_standard'] ?? 25) / 100;  // 25% -> 0.25
        $taux_superieur = ($params['taux_majoration_superieur'] ?? 50) / 100;  // 50% -> 0.50

        // Calcul des majorations
        $heures_majorees = [
            'standard' => 0,
            'superieur' => 0
        ];

        // Calcul des heures majorées en fonction du seuil
        if ($heures_semaine <= $seuil_majoration) {
            // Majoration standard (25%)
            $heures_majorees['standard'] = $duree;
            $montant_standard = $duree * $taux_standard;
            $montant_superieur = 0;
        } else {
            // Majoration supérieure (50%)
            $heures_majorees['superieur'] = $duree;
            $montant_standard = 0;
            $montant_superieur = $duree * $taux_superieur;
        }

        return [
            'heures' => $heures_majorees,
            'montants' => [
                'standard' => $montant_standard,
                'superieur' => $montant_superieur
            ],
            'taux_majoration_standard' => $params['taux_majoration_standard'] ?? 25,
            'taux_majoration_superieur' => $params['taux_majoration_superieur'] ?? 50
        ];
    }

    public function getMonthlyStats($user_id, $annee, $mois) {
        $query = "SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN type_temps = 'heure_supplementaire' AND statut = 'validé' 
                    THEN duree_calculee + majoration_standard + majoration_superieur
                    ELSE 0 
                END
            ), 0) as total_heures_majorees
        FROM heures_supplementaires 
        WHERE user_id = :user_id 
        AND YEAR(date_jour) = :annee 
        AND MONTH(date_jour) = :mois";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':annee', $annee);
        $stmt->bindParam(':mois', $mois);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC)['total_heures_majorees'];
    }

    public function getMonthlyRecups($user_id, $annee, $mois) {
        $query = "SELECT COALESCE(SUM(duree_calculee), 0) as total_recups
                 FROM heures_supplementaires
                 WHERE user_id = :user_id
                 AND YEAR(date_jour) = :annee
                 AND MONTH(date_jour) = :mois
                 AND type_temps = 'recuperation'
                 AND statut = 'validé'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":annee", $annee);
        $stmt->bindParam(":mois", $mois);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_recups'];
    }

    public function getYearlyTotal($user_id, $annee) {
        $query = "SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN type_temps = 'heure_supplementaire' AND statut = 'validé' 
                    THEN duree_calculee + majoration_standard + majoration_superieur
                    ELSE 0 
                END
            ), 0) as total_heures_majorees
        FROM heures_supplementaires 
        WHERE user_id = :user_id 
        AND YEAR(date_jour) = :annee";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':annee', $annee);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC)['total_heures_majorees'];
    }

    public function getAllHours($month, $year) {
        $query = "SELECT hs.*, u.nom, u.prenom, c.nom as client_nom
                FROM " . $this->table_name . " hs
                LEFT JOIN users u ON hs.user_id = u.id
                LEFT JOIN clients c ON hs.client_id = c.id
                WHERE MONTH(hs.date_jour) = :month 
                AND YEAR(hs.date_jour) = :year
                ORDER BY hs.date_jour DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':month', $month);
        $stmt->bindParam(':year', $year);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère toutes les heures supplémentaires d'un utilisateur pour une année donnée
     * @param int $userId ID de l'utilisateur
     * @param int $year Année
     * @return array Liste des heures supplémentaires
     */
    public function getUserHoursByYear($userId, $year) {
        $query = "SELECT h.*, c.nom as client_nom,
                CASE 
                    WHEN h.type_temps = 'recuperation' THEN 'récupération'
                    ELSE 'heure_supplementaire'
                END as type
                FROM heures_supplementaires h 
                LEFT JOIN clients c ON h.client_id = c.id 
                WHERE h.user_id = :user_id 
                AND YEAR(h.date_jour) = :year 
                ORDER BY h.date_jour DESC, h.heure_debut DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':year' => $year
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcule le total des heures supplémentaires pour une année donnée
     * @param int $userId ID de l'utilisateur
     * @param int $year Année
     * @return float Total des heures
     */
    public function calculateYearlyTotal($userId, $year) {
        $query = "SELECT SUM(duree_calculee + majoration_standard + majoration_superieur) as total 
                 FROM heures_supplementaires 
                 WHERE user_id = :user_id 
                 AND YEAR(date_jour) = :year 
                 AND statut = 'validé'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':user_id' => $userId,
            ':year' => $year
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    public function getYearlyRecups($user_id, $year) {
        $query = "SELECT COALESCE(SUM(duree_calculee), 0) as total_recups
                FROM heures_supplementaires
                WHERE user_id = :user_id
                AND YEAR(date_jour) = :year
                AND type_temps = 'recuperation'
                AND statut = 'validé'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':year', $year);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_recups'];
    }

    /**
     * Récupère toutes les heures validées d'un utilisateur
     */
    public function getAllValidatedByUser($user_id) {
        $query = "SELECT * FROM heures_supplementaires 
                 WHERE user_id = ? 
                 AND status = 'validé' 
                 ORDER BY date_jour ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 