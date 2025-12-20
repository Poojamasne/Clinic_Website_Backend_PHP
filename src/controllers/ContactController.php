<?php

require_once __DIR__ . '/../models/Contact.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';

class ContactController {
    private $contactModel;
    
    public function __construct() {
        $this->contactModel = new Contact();
    }
    
    // Method for public contact form submission
    public function submitContact() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $errors = $this->validateContactData($input);
            if (!empty($errors)) {
                return $this->jsonResponse(false, 'Validation failed', ['errors' => $errors], 400);
            }
            
            $contactData = [
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'subject' => $input['subject'],
                'message' => $input['message'],
                'status' => 'unread',
                'responded' => 0
            ];
            
            $contact = $this->contactModel->createContact($contactData);
            
            if ($contact) {
                $createdContact = $this->contactModel->find($contact['id']);
                return $this->jsonResponse(true, 'Message sent successfully', [
                    'contact' => $createdContact
                ]);
            } else {
                return $this->jsonResponse(false, 'Failed to send message', null, 500);
            }
            
        } catch (Exception $e) {
            error_log("Submit contact error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    // Method for admin to get all contacts
    public function getAllContacts() {
        try {
            AuthMiddleware::authenticate();
            
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            
            $filters = [
                'status' => $_GET['status'] ?? '',
                'responded' => $_GET['responded'] ?? '',
                'search' => $_GET['search'] ?? '',
                'sort_by' => $_GET['sort_by'] ?? 'createdAt',
                'sort_order' => $_GET['sort_order'] ?? 'DESC'
            ];
            
            $result = $this->contactModel->getAllPaginated($page, $limit, $filters);
            
            return $this->jsonResponse(true, 'Contacts retrieved', [
                'contacts' => $result['data'],
                'pagination' => [
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'total' => $result['total'],
                    'totalPages' => $result['totalPages']
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Get all contacts error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    // Method to get contact by ID
    public function getContactById($id) {
        try {
            AuthMiddleware::authenticate();
            
            $contact = $this->contactModel->find($id);
            
            if (!$contact) {
                return $this->jsonResponse(false, 'Contact not found', null, 404);
            }
            
            // Mark as read when fetched by admin
            if ($contact['status'] === 'unread') {
                $this->contactModel->update($id, [
                    'status' => 'read',
                    'updatedAt' => date('Y-m-d H:i:s')
                ]);
                $contact['status'] = 'read';
            }
            
            return $this->jsonResponse(true, 'Contact retrieved', [
                'contact' => $contact
            ]);
            
        } catch (Exception $e) {
            error_log("Get contact by ID error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    // Method to update contact status
    public function updateContactStatus($id) {
        try {
            AuthMiddleware::authenticate();
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $contact = $this->contactModel->find($id);
            if (!$contact) {
                return $this->jsonResponse(false, 'Contact not found', null, 404);
            }
            
            $updateData = [];
            
            if (isset($input['status'])) {
                $updateData['status'] = $input['status'];
            }
            if (isset($input['responded'])) {
                $updateData['responded'] = $input['responded'] ? 1 : 0;
            }
            
            $updateData['updatedAt'] = date('Y-m-d H:i:s');
            
            $success = $this->contactModel->update($id, $updateData);
            
            if ($success) {
                $updatedContact = $this->contactModel->find($id);
                return $this->jsonResponse(true, 'Contact updated successfully', [
                    'contact' => $updatedContact
                ]);
            } else {
                return $this->jsonResponse(false, 'Failed to update contact', null, 500);
            }
            
        } catch (Exception $e) {
            error_log("Update contact status error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    // Method to mark contact as read
    public function markAsRead($id) {
        try {
            AuthMiddleware::authenticate();
            
            $contact = $this->contactModel->find($id);
            if (!$contact) {
                return $this->jsonResponse(false, 'Contact not found', null, 404);
            }
            
            $success = $this->contactModel->update($id, [
                'status' => 'read',
                'updatedAt' => date('Y-m-d H:i:s')
            ]);
            
            if ($success) {
                $updatedContact = $this->contactModel->find($id);
                return $this->jsonResponse(true, 'Contact marked as read', [
                    'contact' => $updatedContact

                    
                ]);
            } else {
                return $this->jsonResponse(false, 'Failed to mark contact as read', null, 500);
            }
            

        } catch (Exception $e) {
            error_log("Mark as read error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    // Method to get unread count
    public function getUnreadCount() {
        try {
            AuthMiddleware::authenticate();
            
            $count = $this->contactModel->count(['status' => 'unread']);
            
            return $this->jsonResponse(true, 'Unread count retrieved', [
                'unread_count' => $count
            ]);
            
        } catch (Exception $e) {
            error_log("Get unread count error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    private function validateContactData($data) {
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
        
        if (empty($data['subject'])) {
            $errors['subject'] = 'Subject is required';
        }
        
        if (empty($data['message'])) {
            $errors['message'] = 'Message is required';
        } elseif (strlen($data['message']) < 10) {
            $errors['message'] = 'Message must be at least 10 characters';
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