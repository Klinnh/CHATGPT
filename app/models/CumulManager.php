<?php

class CumulManager {
    private $db;
    private static $instance = null;

    private function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Recalcule les cumuls pour tous les utilisateurs
     */
    public function recalculerTousLesCumuls() {
        try {
            $this->db->beginTransaction();

            // 1. Vider les tables de cumuls
            $this->db->exec("TRUNCATE TABLE cumuls_semaine");
            $this->db->exec("TRUNCATE TABLE cumuls_utilisateur");

            // 2. Récupérer tous les utilisateurs actifs
            $query = "SELECT id FROM users WHERE actif = 1";
            $stmt = $this->db->query($query);
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($users as $user_id) {
                // 3. Récupérer toutes les semaines de déclarations validées
                $query = "SELECT DISTINCT 
                            YEAR(date_jour) as annee,
                            WEEK(date_jour, 3) as semaine
                         FROM heures_supplementaires
                         WHERE user_id = :user_id
                         AND statut = 'validé'
                         ORDER BY annee, semaine";

                $stmt = $this->db->prepare($query);
                $stmt->execute([':user_id' => $user_id]);
                $semaines = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // 4. Calculer cumul pour chaque semaine
                $cumulSemaine = new CumulSemaine($this->db);
                foreach ($semaines as $periode) {
                    $cumulSemaine->calculerCumulSemaine($user_id, $periode['annee'], $periode['semaine']);
                }

                // 5. Mettre à jour le cumul utilisateur global
                $cumulUtilisateur = new CumulUtilisateur($this->db);
                $cumulUtilisateur->calculerCumul($user_id);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur dans recalculerTousLesCumuls: " . $e->getMessage());
            return false;
        }
    }
}
