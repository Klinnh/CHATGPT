<?php
require_once 'BaseModel.php';

class Client extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table_name = 'clients';
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                (nom, code, adresse, actif, created_at) 
                VALUES 
                (:nom, :code, :adresse, :actif, NOW())";

        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':code', $data['code']);
        $stmt->bindParam(':adresse', $data['adresse']);
        $actif = isset($data['actif']) ? $data['actif'] : 1;
        $stmt->bindParam(':actif', $actif);

        return $stmt->execute();
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . "
                SET nom = :nom,
                    code = :code,
                    adresse = :adresse,
                    actif = :actif,
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        
        // Liaison des paramètres
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nom', $data['nom']);
        $stmt->bindParam(':code', $data['code']);
        $stmt->bindParam(':adresse', $data['adresse']);
        $actif = isset($data['actif']) ? $data['actif'] : 1;
        $stmt->bindParam(':actif', $actif);

        return $stmt->execute();
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY nom ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getActive() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE actif = 1 ORDER BY nom ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 