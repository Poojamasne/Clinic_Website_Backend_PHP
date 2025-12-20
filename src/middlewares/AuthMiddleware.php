<?php

require_once __DIR__ . '/../models/Admin.php';
require_once __DIR__ . '/../utils/JWT.php';

class AuthMiddleware {
    
    public static function authenticate() {
        $token = self::getTokenFromRequest();
        
        if (!$token) {
            self::sendUnauthorized('Authentication required. Please login.');
        }
        
        try {
            $decoded = JWT::decode($token);
            
            if (!isset($decoded['admin_id'])) {
                self::sendUnauthorized('Invalid token structure');
            }
            
            $adminModel = new Admin();

            $admin = $adminModel->find($decoded['admin_id']);
            
            if (!$admin) {
                error_log("Admin account not found for ID: " . $decoded['admin_id']);
                self::sendUnauthorized('Admin account not found');
            }
            
            if (!$admin['is_active']) {
                self::sendUnauthorized('Account is deactivated');
            }
            
            
            $GLOBALS['user'] = [
                'id' => $admin['id'],
                'admin_id' => $admin['admin_id'] ?? $admin['id'], 
                'email' => $admin['email'],
                'role' => $admin['role'],
                'name' => $admin['name']
            ];
            
            return true;
            
        } catch (Exception $e) {
            error_log("AuthMiddleware error: " . $e->getMessage());
            self::sendUnauthorized($e->getMessage());
        }
    }
    
    public static function authorize($roles = []) {
        if (!isset($GLOBALS['user'])) {
            self::sendUnauthorized('Authentication required');
        }
        
        if (!empty($roles) && !in_array($GLOBALS['user']['role'], $roles)) {
            self::sendForbidden('You do not have permission to perform this action');
        }
        
        return true;
    }
    
    public static function optional() {
        $token = self::getTokenFromRequest();
        
        if ($token) {
            try {
                $decoded = JWT::decode($token);
                
                
                if (isset($decoded['admin_id'])) {
                    $adminModel = new Admin();
                    $admin = $adminModel->find($decoded['admin_id']);
                    
                    if ($admin && $admin['is_active']) {
                        $GLOBALS['user'] = [
                            'id' => $admin['id'],
                            'admin_id' => $admin['admin_id'] ?? $admin['id'],
                            'email' => $admin['email'],
                            'role' => $admin['role'],
                            'name' => $admin['name']
                        ];
                    }
                }
            } catch (Exception $e) {
                
                error_log("Optional auth error: " . $e->getMessage());
            }
        }
        
        return true;
    }
    
    private static function getTokenFromRequest() {
        
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        
        if (isset($_COOKIE['admin_token'])) {
            return $_COOKIE['admin_token'];
        }
        
        return null;
    }
    
    private static function sendUnauthorized($message) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
    
    private static function sendForbidden($message) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
        exit;
    }
    
    public static function getUser() {
        return $GLOBALS['user'] ?? null;
    }
}