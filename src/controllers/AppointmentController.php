<?php

require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';

class AppointmentController {
    private $appointmentModel;
    
    public function __construct() {
        $this->appointmentModel = new Appointment();
    }

    
    public function bookAppointment() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $errors = $this->validateAppointmentData($input);
            if (!empty($errors)) {
                return $this->jsonResponse(false, 'Validation failed', ['errors' => $errors], 400);
            }
            
            if (!$this->appointmentModel->isTimeSlotAvailable($input['date'], $input['time'])) {
                return $this->jsonResponse(false, 'Selected time slot is not available', null, 409);
            }
            
            $appointmentData = [
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'service' => $input['service'],
                'date' => $input['date'],
                'time' => $input['time'],
                'notes' => $input['notes'] ?? '',
                'amount' => $input['amount'] ?? 800.00,
                'consultation_fee' => $input['consultation_fee'] ?? 500.00,
                'status' => 'pending',
                'is_paid' => 0
            ];
            
            $appointment = $this->appointmentModel->createAppointment($appointmentData);
            
            if ($appointment) {
                $createdAppointment = $this->appointmentModel->find($appointment['id']);
                return $this->jsonResponse(true, 'Appointment booked successfully', [
                    'appointment' => $createdAppointment
                ]);
            } else {
                return $this->jsonResponse(false, 'Failed to book appointment', null, 500);
            }
            
        } catch (Exception $e) {
            error_log("Book appointment error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    public function getAllAppointments() {
        try {
            AuthMiddleware::authenticate();
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            
            $filters = [
                'status' => $_GET['status'] ?? '',
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? '',
                'search' => $_GET['search'] ?? '',
                'sort_by' => $_GET['sort_by'] ?? 'date',
                'sort_order' => $_GET['sort_order'] ?? 'DESC'
            ];
            
            $result = $this->appointmentModel->getAllPaginated($page, $limit, $filters);
            
            return $this->jsonResponse(true, 'Appointments retrieved', [
                'appointments' => $result['data'],
                'pagination' => [
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total' => $result['total'],
                    'totalPages' => $result['totalPages']
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Get all appointments error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
   
    public function getTodayAppointments() {
        try {
            AuthMiddleware::authenticate();
            
            $appointments = $this->appointmentModel->getTodayAppointments();
            
            return $this->jsonResponse(true, 'Today\'s appointments retrieved', [
                'appointments' => $appointments
            ]);
            
        } catch (Exception $e) {
            error_log("Get today appointments error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
   
    public function getAppointmentById($id) {
        try {
            AuthMiddleware::authenticate();
            
            $appointment = $this->appointmentModel->find($id);
            
            if (!$appointment) {
                return $this->jsonResponse(false, 'Appointment not found', null, 404);
            }
            
            return $this->jsonResponse(true, 'Appointment retrieved', [
                'appointment' => $appointment
            ]);
            
        } catch (Exception $e) {
            error_log("Get appointment by ID error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    
    public function updateAppointmentStatus($id) {
        try {
            AuthMiddleware::authenticate();
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['status'])) {
                return $this->jsonResponse(false, 'Status is required', null, 400);
            }
            
            $validStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
            if (!in_array($input['status'], $validStatuses)) {
                return $this->jsonResponse(false, 'Invalid status', null, 400);
            }
            
            $appointment = $this->appointmentModel->find($id);
            if (!$appointment) {
                return $this->jsonResponse(false, 'Appointment not found', null, 404);
            }
            
            $success = $this->appointmentModel->update($id, [
                'status' => $input['status'],
                'updatedAt' => date('Y-m-d H:i:s')
            ]);
            
            if ($success) {
                $updatedAppointment = $this->appointmentModel->find($id);
                return $this->jsonResponse(true, 'Appointment status updated', [
                    'appointment' => $updatedAppointment
                ]);
            } else {
                return $this->jsonResponse(false, 'Failed to update appointment status', null, 500);
            }
            
        } catch (Exception $e) {
            error_log("Update appointment status error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    
    public function getAppointmentStats() {
        try {
            AuthMiddleware::authenticate();
            
            $stats = $this->appointmentModel->getStats();
            
            return $this->jsonResponse(true, 'Appointment statistics retrieved', [
                'stats' => $stats
            ]);
            
        } catch (Exception $e) {
            error_log("Get appointment stats error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    private function validateAppointmentData($data) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) < 2) {
            $errors['name'] = 'Name must be at least 2 characters';
        }
        
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (empty($data['phone'])) {
            $errors['phone'] = 'Phone is required';
        } elseif (!preg_match('/^[0-9+\-\s()]{10,20}$/', $data['phone'])) {
            $errors['phone'] = 'Invalid phone number';
        }
        
        if (empty($data['service'])) {
            $errors['service'] = 'Service/treatment is required';
        }
        
        if (empty($data['date'])) {
            $errors['date'] = 'Date is required';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
            $errors['date'] = 'Invalid date format (YYYY-MM-DD required)';
        } else {
            $selectedDate = new DateTime($data['date']);
            $tomorrow = new DateTime('tomorrow');
            if ($selectedDate < $tomorrow) {
                $errors['date'] = 'Date must be at least tomorrow';
            }
            
            $threeMonthsLater = new DateTime('+3 months');
            if ($selectedDate > $threeMonthsLater) {
                $errors['date'] = 'Date cannot be more than 3 months in advance';
            }
        }
        
        if (empty($data['time'])) {
            $errors['time'] = 'Time is required';
        }
        
        return $errors;
    }
    
    private function jsonResponse($success, $message, $data = null, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => $success, 
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
}