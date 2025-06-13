<?php
require_once 'database.php';

class Parametres {
    private $db;
    private static $instance = null;
    private $parametres = [];

    private function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->chargerParametres();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function chargerParametres() {
        $query = "SELECT code, valeur FROM parametres_temps";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->parametres[$row['code']] = $row['valeur'];
        }
    }

    public function getParametre($code, $defaut = null) {
        return $this->parametres[$code] ?? $defaut;
    }

    public function getHeuresContractuelles() {
        return floatval($this->getParametre('heures_semaine_contractuelle', 39));
    }

    public function getSeuilMajoration25() {
        return floatval($this->getParametre('seuil_majoration_25', 8));
    }

    public function getSeuilMajoration50() {
        return floatval($this->getParametre('seuil_majoration_50', 8));
    }

    public function getTauxMajoration25() {
        return floatval($this->getParametre('taux_majoration_25', 0.25));
    }

    public function getTauxMajoration50() {
        return floatval($this->getParametre('taux_majoration_50', 0.50));
    }
} 