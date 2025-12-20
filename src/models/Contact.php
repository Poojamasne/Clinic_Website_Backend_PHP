<?php

require_once 'Model.php';

class Contact extends Model {
    protected $table = 'contacts';
    protected $primaryKey = 'id';
    
    public function __construct() {
        parent::__construct();
    }
    
    public function createContact($data) {
        if (!isset($data['status'])) {
            $data['status'] = 'unread';
        }
        if (!isset($data['responded'])) {
            $data['responded'] = 0;
        }
        
        $data['id'] = $this->generateUuid();
        $data['createdAt'] = date('Y-m-d H:i:s');
        $data['updatedAt'] = date('Y-m-d H:i:s');
        
        return $this->create($data);
    }
    
    public function getAllPaginated($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['responded'])) {
            $where[] = 'responded = ?';
            $params[] = $filters['responded'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR subject LIKE ?)';
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as count FROM {$this->table} $whereClause";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['count'];
        
        $orderBy = $filters['sort_by'] ?? 'createdAt';
        $orderDir = strtoupper($filters['sort_order'] ?? 'DESC');
        
        $dataSql = "SELECT * FROM {$this->table} 
                   $whereClause 
                   ORDER BY $orderBy $orderDir 
                   LIMIT ? OFFSET ?";
        
        $dataParams = array_merge($params, [$limit, $offset]);
        $dataStmt = $this->db->prepare($dataSql);
        $dataStmt->execute($dataParams);
        $data = $dataStmt->fetchAll();
        
        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ];
    }
    
    public function getStats() {
        $stats = [
            'total' => $this->count(),
            'unread' => $this->count(['status' => 'unread']),
            'read' => $this->count(['status' => 'read']),
            'responded' => $this->count(['responded' => 1]),
            'not_responded' => $this->count(['responded' => 0]),
            'today' => $this->count(['DATE(createdAt)' => date('Y-m-d')]),
            'yesterday' => $this->count(['DATE(createdAt)' => date('Y-m-d', strtotime('-1 day'))])
        ];
        
        return $stats;
    }
}