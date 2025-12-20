<?php

class Model {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct() {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }
    
    public function create($data) {
    try {
        
        if (!isset($data['createdAt'])) {
            $data['createdAt'] = date('Y-m-d H:i:s');
        }
        if (!isset($data['updatedAt'])) {
            
            $data['updatedAt'] = date('Y-m-d H:i:s');
        }
        
        $fields = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$this->table} ($fields) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        
        $result = $stmt->execute($data);
        
        
        if ($result) {
            
            $insertedId = $this->db->lastInsertId();
            
            
            if (!$insertedId && isset($data['id'])) {
                $insertedId = $data['id'];
            }
            
            
            if ($insertedId) {
                return $this->find($insertedId);
            }
            
           
            return $this->findByCreatedData($data);
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error creating record in {$this->table}: " . $e->getMessage());
        throw $e;
    }
}


private function findByCreatedData($data) {
    try {
        $where = [];
        $params = [];
        
        
        foreach (['id', 'patient_email', 'patient_phone', 'createdAt'] as $field) {
            if (isset($data[$field])) {
                $where[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (!empty($where)) {
            $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . " ORDER BY createdAt DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error finding created record: " . $e->getMessage());
        return false;
    }
}
    
    public function update($id, $data) {
        try {
            
            $data['updatedAt'] = date('Y-m-d H:i:s');
            
            $fields = [];
            foreach ($data as $key => $value) {
                $fields[] = "$key = :$key";
            }
            
            $data[$this->primaryKey] = $id;
            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . 
                   " WHERE {$this->primaryKey} = :{$this->primaryKey}";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute($data);
        } catch (Exception $e) {
            error_log("Error updating record in {$this->table}: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function find($id) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error finding record in {$this->table}: " . $e->getMessage());
            return false;
        }
    }
    
    public function findAll($conditions = [], $orderBy = null, $limit = null) {
        try {
            $sql = "SELECT * FROM {$this->table}";
            $params = [];
            
            if (!empty($conditions)) {
                $where = [];
                foreach ($conditions as $field => $value) {
                    $where[] = "$field = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            
            if ($orderBy) {
                $sql .= " ORDER BY $orderBy";
            }
            
            if ($limit) {
                $sql .= " LIMIT $limit";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error finding all records in {$this->table}: " . $e->getMessage());
            return [];
        }
    }
    
    public function count($conditions = []) {
        try {
            $sql = "SELECT COUNT(*) as count FROM {$this->table}";
            $params = [];
            
            if (!empty($conditions)) {
                $where = [];
                foreach ($conditions as $field => $value) {
                    $where[] = "$field = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch()['count'];
        } catch (Exception $e) {
            error_log("Error counting records in {$this->table}: " . $e->getMessage());
            return 0;
        }
    }
    
    public function delete($id) {
        try {
            $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Error deleting record from {$this->table}: " . $e->getMessage());
            return false;
        }
    }
    
    protected function generateUuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}