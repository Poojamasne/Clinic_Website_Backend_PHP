<?php

require_once 'Model.php';

class Admin extends Model {
    protected $table = 'admins';
    protected $primaryKey = 'id';
    
    public function __construct() {
        parent::__construct();
    }
    
    public function createAdmin($data) {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        if (!isset($data['is_active'])) {
            $data['is_active'] = 1; 
        }
        
        return $this->create($data);
    }
    
    public function findByEmail($email) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ?");
            $stmt->execute([strtolower(trim($email))]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                error_log("Admin not found with email: $email");
                return false;
            }
            
            return $admin;
        } catch (Exception $e) {
            error_log("Error in findByEmail: " . $e->getMessage());
            return false;
        }
    }
    
    public function verifyPassword($password, $hash) {
   
    if (password_verify($password, $hash)) {
        return true;
    }
    
    if (strlen($hash) === 64 && ctype_xdigit($hash)) {
        $hashedInput = hash('sha256', $password);
        return hash_equals($hash, $hashedInput);
    }
    
    return false;
}
    
    public function updateLastLogin($id) {
    try {
        
        $sql = "UPDATE {$this->table} SET last_login = ?, updatedAt = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $currentTime = date('Y-m-d H:i:s');
        return $stmt->execute([$currentTime, $currentTime, $id]);
    } catch (Exception $e) {
        error_log("Error updating last login: " . $e->getMessage());
        return false;
    }
}

    public function updateAdmin($id, $data) {
    try {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        $data['updatedAt'] = date('Y-m-d H:i:s');
        
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "$field = ?";
            $values[] = $value;
        }
        
        $values[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    } catch (Exception $e) {
        error_log("Error updating admin: " . $e->getMessage());
        return false;
    }
}
    
    public function find($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error finding admin: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAll($page = 1, $limit = 20, $search = '') {
        $offset = ($page - 1) * $limit;
        $params = [];
        
        $sql = "SELECT id, email, name, role, is_active, last_login, createdAt 
                FROM {$this->table}";
        
        if (!empty($search)) {
            $sql .= " WHERE email LIKE ? OR name LIKE ?";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm];
        }
        
        $sql .= " ORDER BY createdAt DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function countAll($search = '') {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];
        
        if (!empty($search)) {
            $sql .= " WHERE email LIKE ? OR name LIKE ?";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch()['count'];
    }
}