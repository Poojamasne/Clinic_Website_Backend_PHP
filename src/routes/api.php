<?php

require_once __DIR__ . '/../controllers/AppointmentController.php';
require_once __DIR__ . '/../controllers/ContactController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../controllers/PaymentController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get request URI and method
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove base path if needed
$basePath = '/Clinic_Website_Backend_PHP';
if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

// Define routes
$routes = [
    // Appointment routes
    'POST /api/appointments' => function() {
        $controller = new AppointmentController();
        $controller->create();
    },
    'GET /api/appointments' => function() {
        $controller = new AppointmentController();
        $controller->getAll();
    },
    'GET /api/appointments/availability' => function() {
        $controller = new AppointmentController();
        $controller->checkAvailability();
    },
    'GET /api/appointments/stats' => function() {
        $controller = new AppointmentController();
        $controller->getStats();
    },
    'GET /api/appointments/{id}' => function($id) {
        $controller = new AppointmentController();
        $controller->get($id);
    },
    'PUT /api/appointments/{id}' => function($id) {
        $controller = new AppointmentController();
        $controller->update($id);
    },
    'DELETE /api/appointments/{id}' => function($id) {
        $controller = new AppointmentController();
        $controller->delete($id);
    },
    'POST /api/appointments/{id}/payment-status' => function($id) {
        $controller = new AppointmentController();
        $controller->updatePaymentStatus($id);
    },
    
    // Contact routes
    'POST /api/contacts' => function() {
        $controller = new ContactController();
        $controller->create();
    },
    'GET /api/contacts' => function() {
        $controller = new ContactController();
        $controller->getAll();
    },
    'GET /api/contacts/stats' => function() {
        $controller = new ContactController();
        $controller->getStats();
    },
    'GET /api/contacts/{id}' => function($id) {
        $controller = new ContactController();
        $controller->get($id);
    },
    'PUT /api/contacts/{id}' => function($id) {
        $controller = new ContactController();
        $controller->update($id);
    },
    'DELETE /api/contacts/{id}' => function($id) {
        $controller = new ContactController();
        $controller->delete($id);
    },
    
    // Admin routes
    'POST /api/admin/login' => function() {
        $controller = new AdminController();
        $controller->login();
    },
    'POST /api/admin/logout' => function() {
        $controller = new AdminController();
        $controller->logout();
    },
    'GET /api/admin/profile' => function() {
        $controller = new AdminController();
        $controller->getProfile();
    },
    'PUT /api/admin/profile' => function() {
        $controller = new AdminController();
        $controller->updateProfile();
    },
    'GET /api/admin/verify-token' => function() {
        $controller = new AdminController();
        $controller->verifyToken();
    },
    
    // Payment routes
    'POST /api/payments/create' => function() {
        $controller = new PaymentController();
        $controller->createPayment();
    },
    'POST /api/payments/verify' => function() {
        $controller = new PaymentController();
        $controller->verifyPayment();
    },
];

// Route matching
$matched = false;
foreach ($routes as $route => $handler) {
    list($method, $pathPattern) = explode(' ', $route, 2);
    
    if ($requestMethod === $method) {
        // Replace {id} with regex pattern
        $pathRegex = preg_replace('/\{[^}]+\}/', '([^/]+)', $pathPattern);
        $pathRegex = str_replace('/', '\/', $pathRegex);
        $pathRegex = '/^' . $pathRegex . '$/';
        
        if (preg_match($pathRegex, $requestUri, $matches)) {
            $matched = true;
            array_shift($matches); // Remove full match
            
            // Call handler with parameters
            call_user_func_array($handler, $matches);
            break;
        }
    }
}

if (!$matched) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Endpoint not found'
    ]);
}