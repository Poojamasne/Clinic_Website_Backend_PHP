<?php

require_once __DIR__ . '/../models/Admin.php';
require_once __DIR__ . '/../utils/JWT.php';

class AdminController {
    private $adminModel;
    
    public function __construct() {
        $this->adminModel = new Admin();
    }
    
    public function login($request) {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['email']) || empty($data['password'])) {
                return $this->jsonResponse(false, 'Email and password are required', null, 400);
            }
            
            $email = strtolower(trim($data['email']));
            
            error_log("Looking for admin with email: " . $email);
            
            $admin = $this->adminModel->findByEmail($email);
            
            if (!$admin) {
                error_log("Login failed: Admin not found with email " . $email);
                return $this->jsonResponse(false, 'Invalid email or password', null, 401);
            }
            
            error_log("Found admin - ID: " . $admin['id'] . ", Email: " . $admin['email'] . ", is_active: " . $admin['is_active']);
            
            if (!isset($admin['is_active']) || $admin['is_active'] != 1) {
                $isActive = isset($admin['is_active']) ? $admin['is_active'] : 'not set';
                error_log("Login failed: Account is_active = '$isActive' for email " . $email);
                return $this->jsonResponse(false, 'Account is deactivated', null, 403);
            }
            
            if (!$this->adminModel->verifyPassword($data['password'], $admin['password'])) {
                error_log("Login failed: Invalid password for email " . $email);
                return $this->jsonResponse(false, 'Invalid email or password', null, 401);
            }
            
            $this->adminModel->updateLastLogin($admin['id']);
            
            $tokenData = [
                'admin_id' => $admin['id'],
                'email' => $admin['email'],
                'role' => isset($admin['role']) ? $admin['role'] : 'admin',
                'name' => isset($admin['name']) ? $admin['name'] : 'Admin',
                'exp' => time() + (24 * 60 * 60) // 24 hours
            ];
            
            $jwtSecret = getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production';
            $token = JWT::encode($tokenData, $jwtSecret);
            
            
            $adminResponse = [
                'id' => $admin['id'],
                'name' => $admin['name'],
                'email' => $admin['email'],
                'role' => $admin['role'],
                'is_active' => $admin['is_active'],
                'last_login' => $admin['last_login']
            ];
            
            return $this->jsonResponse(true, 'Login successful', [
                'token' => $token,
                'admin' => $adminResponse
            ]);
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    public function logout() {
        setcookie('admin_token', '', time() - 3600, '/');
        return $this->jsonResponse(true, 'Logged out successfully');
    }
    
    public function getProfile() {
        try {
            $user = AuthMiddleware::getUser();
            
            if (!$user) {
                return $this->jsonResponse(false, 'Unauthorized', null, 401);
            }
            
            $admin = $this->adminModel->find($user['admin_id']);
            if (!$admin) {
                return $this->jsonResponse(false, 'Admin not found', null, 404);
            }
            
            unset($admin['password']);
            
            return $this->jsonResponse(true, 'Profile retrieved', [
                'admin' => $admin
            ]);
            
        } catch (Exception $e) {
            error_log("Get profile error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    public function updateProfile() {
        try {
            $user = AuthMiddleware::getUser();
            
            if (!$user) {
                return $this->jsonResponse(false, 'Unauthorized', null, 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $admin = $this->adminModel->find($user['admin_id']);
            if (!$admin) {
                return $this->jsonResponse(false, 'Admin not found', null, 404);
            }
            
            $updates = [];
            $errors = [];
            

            if (!empty($data['name'])) {
                $name = trim($data['name']);
                if (strlen($name) < 2) {
                    $errors[] = 'Name must be at least 2 characters';
                } elseif (strlen($name) > 100) {
                    $errors[] = 'Name cannot exceed 100 characters';
                } else {
                    $updates['name'] = $name;
                }
            }
            
            
            if (!empty($data['current_password']) && !empty($data['new_password'])) {
                if (!$this->adminModel->verifyPassword($data['current_password'], $admin['password'])) {
                    $errors[] = 'Current password is incorrect';
                }
                
                if (strlen($data['new_password']) < 6) {
                    $errors[] = 'New password must be at least 6 characters';
                }
                
                if (empty($errors)) {
                    $updates['password'] = $data['new_password'];
                }
            }
            
            if (!empty($errors)) {
                return $this->jsonResponse(false, 'Validation failed', ['details' => $errors], 400);
            }
            
            if (!empty($updates)) {
                $this->adminModel->updateAdmin($admin['id'], $updates);
            }
            
           
            $updatedAdmin = $this->adminModel->find($admin['id']);
            unset($updatedAdmin['password']);
            
            return $this->jsonResponse(true, 'Profile updated successfully', [
                'admin' => $updatedAdmin
            ]);
            
        } catch (Exception $e) {
            error_log("Update profile error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    public function verifyToken() {
        try {
            $user = AuthMiddleware::getUser();
            
            if (!$user) {
                return $this->jsonResponse(false, 'Invalid token', null, 401);
            }
            
            return $this->jsonResponse(true, 'Token is valid', [
                'admin' => $user
            ]);
            
        } catch (Exception $e) {
            error_log("Verify token error: " . $e->getMessage());
            return $this->jsonResponse(false, 'Internal server error', null, 500);
        }
    }
    
    private function jsonResponse($success, $message, $data = null, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        
        $response = ['success' => $success, 'message' => $message];
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
}