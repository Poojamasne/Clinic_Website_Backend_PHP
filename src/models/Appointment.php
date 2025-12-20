<?php

require_once 'Model.php';

class Appointment extends Model {
    protected $table = 'appointments';
    protected $primaryKey = 'id';
    
    public function __construct() {
        parent::__construct();
    }
    
    public function createAppointment($data) {
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }
        if (!isset($data['is_paid'])) {
            $data['is_paid'] = 0;
        }
        
        $data['id'] = $this->generateUuid();
        $data['createdAt'] = date('Y-m-d H:i:s');
        $data['updatedAt'] = date('Y-m-d H:i:s');
        
        return $this->create($data);
    }
    
    public function findByDateTime($date, $time, $excludeId = null) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE date = ? 
                AND time = ? 
                AND status IN ('pending', 'confirmed')";
        
        $params = [$date, $time];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    public function getAllPaginated($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'date >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'date <= ?';
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $search = "%{$filters['search']}%";
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
        
        $orderBy = $filters['sort_by'] ?? 'date';
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
    
    public function getTodayAppointments() {
        $today = date('Y-m-d');
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE date = ? 
            ORDER BY time ASC
        ");
        $stmt->execute([$today]);
        return $stmt->fetchAll();
    }
    
    public function getStats() {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $stats = [
            'total' => $this->count(),
            'pending' => $this->count(['status' => 'pending']),
            'confirmed' => $this->count(['status' => 'confirmed']),
            'cancelled' => $this->count(['status' => 'cancelled']),
            'completed' => $this->count(['status' => 'completed']),
            'today' => $this->count(['date' => $today]),
            'yesterday' => $this->count(['date' => $yesterday]),
            'paid' => $this->count(['is_paid' => 1]),
            'unpaid' => $this->count(['is_paid' => 0])
        ];
        
        return $stats;
    }
    
    public function updatePaymentStatus($id, $paymentId, $isPaid = true) {
        return $this->update($id, [
            'payment_id' => $paymentId,
            'is_paid' => $isPaid ? 1 : 0,
            'status' => 'confirmed',
            'updatedAt' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function isTimeSlotAvailable($date, $time, $excludeId = null) {
        $existing = $this->findByDateTime($date, $time, $excludeId);
        return empty($existing);
    }
}