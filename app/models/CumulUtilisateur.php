<?php

class CumulUtilisateur {
    private $conn;
    private $table_name = "cumuls_utilisateur";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Récupère le cumul d'un utilisateur
     */
    public function getCumulUtilisateur($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour ou crée le cumul d'un utilisateur
     */
    public function updateCumul($user_id, $data) {
        // Vérifie si l'utilisateur a déjà un cumul
        $cumul = $this->getCumulUtilisateur($user_id);
        
        if ($cumul) {
            $query = "UPDATE " . $this->table_name . " SET 
                total_heures_sup = ?,
                total_heures_maj_n1 = ?,
                total_heures_maj_n2 = ?,
                total_heures_recup = ?,
                derniere_semaine_calculee = ?,
                derniere_annee_calculee = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ?";
            
            return $this->conn->prepare($query)->execute([
                $data['total_heures_sup'],
                $data['total_heures_maj_n1'],
                $data['total_heures_maj_n2'],
                $data['total_heures_recup'],
                $data['derniere_semaine_calculee'],
                $data['derniere_annee_calculee'],
                $user_id
            ]);
        } else {
            $query = "INSERT INTO " . $this->table_name . " 
                (user_id, total_heures_sup, total_heures_maj_n1, total_heures_maj_n2, 
                total_heures_recup, derniere_semaine_calculee, derniere_annee_calculee) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            return $this->conn->prepare($query)->execute([
                $user_id,
                $data['total_heures_sup'],
                $data['total_heures_maj_n1'],
                $data['total_heures_maj_n2'],
                $data['total_heures_recup'],
                $data['derniere_semaine_calculee'],
                $data['derniere_annee_calculee']
            ]);
        }
    }

    /**
     * Calcule et met à jour le cumul d'un utilisateur
     */
    public function calculerCumul($user_id) {
        // Calculer les cumuls à partir des données hebdomadaires
        $query = "INSERT INTO " . $this->table_name . " 
            (user_id, total_heures_sup, total_heures_recup, solde_actuel)
            SELECT 
                user_id,
                SUM(total_heures_sup) as total_heures_sup,
                SUM(total_heures_recup) as total_heures_recup,
                SUM(solde_semaine) as solde_actuel
            FROM cumuls_semaine
            WHERE user_id = ?
            GROUP BY user_id
            ON DUPLICATE KEY UPDATE
                total_heures_sup = VALUES(total_heures_sup),
                total_heures_recup = VALUES(total_heures_recup),
                solde_actuel = VALUES(solde_actuel)";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$user_id]);
    }

    /**
     * Met à jour ou crée un cumul utilisateur avec les nouveaux champs
     */
    public function updateOrCreate($user_id, $data) {
        // Vérifie si l'utilisateur a déjà un cumul
        $cumul = $this->getCumulUtilisateur($user_id);
        
        if ($cumul) {
            $query = "UPDATE " . $this->table_name . " SET 
                total_heures_sup = ?,
                total_heures_recup = ?,
                solde_actuel = ?,
                derniere_maj = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ?";
            
            return $this->conn->prepare($query)->execute([
                $data['total_heures_sup'],
                $data['total_heures_recup'],
                $data['solde_actuel'],
                $data['derniere_maj'],
                $user_id
            ]);
        } else {
            $query = "INSERT INTO " . $this->table_name . " 
                (user_id, total_heures_sup, total_heures_recup, solde_actuel, derniere_maj, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
            
            return $this->conn->prepare($query)->execute([
                $user_id,
                $data['total_heures_sup'],
                $data['total_heures_recup'],
                $data['solde_actuel'],
                $data['derniere_maj']
            ]);
        }
    }

    public function getCumulsGlobaux() {
        $query = "SELECT 
            SUM(total_heures_sup) as total_hs,
            SUM(total_heures_recup) as total_recup
            FROM " . $this->table_name;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
} 