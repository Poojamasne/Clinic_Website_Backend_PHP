<?php

require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once 'src/config/database.php';
require_once 'src/utils/JWT.php';
require_once 'src/middlewares/AuthMiddleware.php';

header('Access-Control-Allow-Origin: ' . getenv('ALLOWED_ORIGINS') ?: '*');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

$requestUri = strtok($requestUri, '?');


$routes = [
    
    ['GET', '/health', function() {
        echo json_encode([
            'status' => 'OK',
            'message' => 'Server is running',
            'timestamp' => date('c'),
            'environment' => getenv('APP_ENV') ?: 'development'
        ]);
    }],
    
    // Admin routes
    ['POST', '/api/admin/login', function() {
        $controller = new AdminController();
        $controller->login([]);
    }],
    
    ['POST', '/api/admin/logout', function() {
        AuthMiddleware::authenticate();
        $controller = new AdminController();
        $controller->logout();
    }],
    
    ['GET', '/api/admin/profile', function() {
        AuthMiddleware::authenticate();
        $controller = new AdminController();
        $controller->getProfile();
    }],
    
    ['PUT', '/api/admin/profile', function() {
        AuthMiddleware::authenticate();
        $controller = new AdminController();
        $controller->updateProfile();
    }],
    
    ['GET', '/api/admin/verify-token', function() {
        AuthMiddleware::authenticate();
        $controller = new AdminController();
        $controller->verifyToken();
    }],
    
    // Appointment routes (Public)
    ['POST', '/api/appointments', function() {
        $controller = new AppointmentController();
        $controller->bookAppointment();
    }],
    
    // Appointment routes (Admin)
    ['GET', '/api/appointments/admin', function() {
        AuthMiddleware::authenticate();
        $controller = new AppointmentController();
        $controller->getAllAppointments();
    }],
    
    ['GET', '/api/appointments/admin/stats', function() {
        AuthMiddleware::authenticate();
        $controller = new AppointmentController();
        $controller->getAppointmentStats();
    }],
    
    ['GET', '/api/appointments/admin/today', function() {
        AuthMiddleware::authenticate();
        $controller = new AppointmentController();
        $controller->getTodayAppointments();
    }],
    
    ['GET', '/api/appointments/admin/([^/]+)', function($id) {
        AuthMiddleware::authenticate();
        $controller = new AppointmentController();
        $controller->getAppointmentById($id);
    }],
    
    ['PUT', '/api/appointments/admin/([^/]+)', function($id) {
        AuthMiddleware::authenticate();
        $controller = new AppointmentController();
        $controller->updateAppointmentStatus($id);
    }],
    
    // Contact routes (Public)
    ['POST', '/api/contact', function() {
        $controller = new ContactController();
        $controller->submitContact();
    }],
    
    // Contact routes (Admin)
    ['GET', '/api/contact/admin', function() {
        AuthMiddleware::authenticate();
        $controller = new ContactController();
        $controller->getAllContacts();
    }],
    
    ['GET', '/api/contact/admin/unread-count', function() {
        AuthMiddleware::authenticate();
        $controller = new ContactController();
        $controller->getUnreadCount();
    }],
    
    ['GET', '/api/contact/admin/([^/]+)', function($id) {
        AuthMiddleware::authenticate();
        $controller = new ContactController();
        $controller->getContactById($id);
    }],
    
    ['PUT', '/api/contact/admin/([^/]+)', function($id) {
        AuthMiddleware::authenticate();
        $controller = new ContactController();
        $controller->updateContactStatus($id);
    }],
    
    ['PUT', '/api/contact/admin/([^/]+)/read', function($id) {
        AuthMiddleware::authenticate();
        $controller = new ContactController();
        $controller->markAsRead($id);
    }],
    
    // Payment routes
    ['POST', '/api/payment/order', function() {
        try {
            $controller = new PaymentController();
            $controller->createOrder();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Payment service not available']);
        }
    }],
    
    ['POST', '/api/payment/verify', function() {
        AuthMiddleware::authenticate();
        try {
            $controller = new PaymentController();
            $controller->verifyPayment();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Payment verification failed']);
        }
    }],
    
    ['GET', '/api/payment/details/([^/]+)', function($appointmentId) {
        AuthMiddleware::authenticate();
        try {
            $controller = new PaymentController();
            $controller->getPaymentDetails($appointmentId);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Payment details unavailable']);
        }
    }],
    
    ['GET', '/api/payment/status/([^/]+)', function($appointmentId) {
        AuthMiddleware::authenticate();
        try {
            $controller = new PaymentController();
            $controller->checkPaymentStatus($appointmentId);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Payment status unavailable']);
        }
    }]
];


function matchRoute($method, $uri, $route) {
    list($routeMethod, $routePattern, $handler) = $route;
    
    
    if ($routeMethod !== $method) {
        return false;
    }
    
    
    if ($routePattern === $uri) {
        return ['handler' => $handler, 'params' => []];
    }
    
    
    if (strpos($routePattern, '(') !== false) {
        $pattern = '#^' . str_replace('/', '\/', $routePattern) . '$#';
        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches);
            return ['handler' => $handler, 'params' => $matches];
        }
    }
    
    return false;
}


$matched = false;
foreach ($routes as $route) {
    $match = matchRoute($requestMethod, $requestUri, $route);
    if ($match) {
        
        spl_autoload_register(function ($class) {
            $file = __DIR__ . '/src/controllers/' . $class . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });
        
        
        spl_autoload_register(function ($class) {
            $file = __DIR__ . '/src/models/' . $class . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });
        
        
        call_user_func_array($match['handler'], $match['params']);
        $matched = true;
        break;
    }
}


if (!$matched) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Route not found',
        'path' => $requestUri,
        'method' => $requestMethod
    ]);
}


set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error: $errstr in $errfile on line $errline");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => getenv('APP_ENV') === 'development' ? $errstr : 'Something went wrong'
    ]);
    exit;
});

set_exception_handler(function($exception) {
    error_log("Exception: " . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => getenv('APP_ENV') === 'development' ? $exception->getMessage() : 'Something went wrong'
    ]);
    exit;
});