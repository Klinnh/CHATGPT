<?php
class BaseModel {
    protected $conn;
    protected $table_name;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Méthode pour lire tous les enregistrements
    public function read() {
        $query = "SELECT * FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Méthode pour lire un enregistrement par son ID
    public function readOne($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        return $stmt;
    }

    // Méthode pour créer un enregistrement
    public function create($data) {
        $fields = array_keys($data);
        $values = array_fill(0, count($fields), '?');
        
        $query = "INSERT INTO " . $this->table_name . " (" . implode(', ', $fields) . ") 
                 VALUES (" . implode(', ', $values) . ")";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(array_values($data));
    }

    // Méthode pour mettre à jour un enregistrement
    public function update($id, $data) {
        $fields = array_map(function($field) {
            return $field . " = ?";
        }, array_keys($data));
        
        $query = "UPDATE " . $this->table_name . " 
                 SET " . implode(', ', $fields) . " 
                 WHERE id = ?";
        
        $values = array_values($data);
        $values[] = $id;
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($values);
    }

    // Méthode pour supprimer un enregistrement
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }
} 