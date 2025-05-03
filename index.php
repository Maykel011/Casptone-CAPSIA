<?php
// Error reporting configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define application paths
define('BASE_PATH', __DIR__);
define('ADMIN_PATH', BASE_PATH . '/admin');
define('USER_PATH', BASE_PATH . '/user');
define('LOGIN_PATH', BASE_PATH . '/login');
define('API_PATH', BASE_PATH . '/api');
define('ASSETS_PATH', BASE_PATH . '/assets');

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'name' => 'SecureSessionID',
        'cookie_lifetime' => 86400,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'use_only_cookies' => 1
    ]);
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:");

// Authentication check function
function is_authenticated() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Administrator';
}

// Routing function
function route_request() {
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $request_path = trim($request_uri, '/');
    
    // Static assets - let server handle directly
    if (preg_match('/\.(css|js|jpg|jpeg|png|gif|ico|pdf)$/i', $request_uri)) {
        return false;
    }
    
    // API endpoints
    if (strpos($request_path, 'api/') === 0) {
        $api_endpoint = substr($request_path, 4);
        handle_api_request($api_endpoint);
        return true;
    }
    
    // Admin routes
    if (strpos($request_path, 'admin/') === 0) {
        if (!is_authenticated() || !is_admin()) {
            header('Location: /login/login.php');
            exit();
        }
        $admin_route = substr($request_path, 6);
        handle_admin_request($admin_route);
        return true;
    }
    
    // User routes
    if (strpos($request_path, 'user/') === 0) {
        if (!is_authenticated()) {
            header('Location: /login/login.php');
            exit();
        }
        $user_route = substr($request_path, 5);
        handle_user_request($user_route);
        return true;
    }
    
    // Login routes
    if ($request_path === 'login' || strpos($request_path, 'login/') === 0) {
        $login_route = substr($request_path, 6);
        handle_login_request($login_route);
        return true;
    }
    
    // Root requests
    if (empty($request_path) || $request_path === 'index.php') {
        if (is_authenticated()) {
            if (is_admin()) {
                header('Location: /admin/adminDashboard.php');
            } else {
                header('Location: /user/Userdashboard.php');
            }
            exit();
        } else {
            header('Location: /login/login.php');
            exit();
        }
    }
    
    // 404 Not Found
    http_response_code(404);
    if (file_exists(__DIR__ . '/login/not-found.php')) {
        include __DIR__ . '/login/not-found.php';
    } else {
        die('<h1>404 Not Found</h1><p>The requested page was not found.</p>');
    }
    exit();
}
// Route handlers
function handle_admin_request($route) {
    $admin_routes = [
        '' => 'adminDashboard.php',
        'dashboard' => 'adminDashboard.php',
        'notification' => 'adminnotification.php',
        'profile' => 'adminprofile.php',
        'handlerequest' => 'handleRequest.php',
        'itemborrowed' => 'ItemBorrowed.php',
        'itemrecords' => 'ItemRecords.php',
        'itemrequest' => 'ItemRequest.php',
        'itemreturned' => 'ItemReturned.php',
        'processrequest' => 'processRequest.php',
        'reports' => 'Reports.php',
        'approverequest' => 'updateRequestStatusApprove.php',
        'rejectrequest' => 'updateRequestStatusReject.php',
        'usermanagement' => 'UserManagement.php'
    ];
    
    if (isset($admin_routes[$route]) && file_exists(__DIR__ . '/admin/' . $admin_routes[$route])) {
        require __DIR__ . '/admin/' . $admin_routes[$route];
    } else {
        http_response_code(404);
        require __DIR__ . '/login/not-found.php';
        exit();
    }
}

function handle_user_request($route) {
    $user_routes = [
        '' => 'Userdashboard.php',
        'dashboard' => 'Userdashboard.php',
        'itemborrow' => 'UserItemBorrow.php',
        'itemrecords' => 'UserItemRecords.php',
        'itemrequests' => 'UserItemRequests.php',
        'itemreturned' => 'UserItemReturned.php',
        'notification' => 'usernotification.php',
        'profile' => 'userprofile.php',
        'transaction' => 'UserTransaction.php'
    ];
    
    if (isset($user_routes[$route]) && file_exists(__DIR__ . '/user/' . $user_routes[$route])) {
        require __DIR__ . '/user/' . $user_routes[$route];
    } else {
        http_response_code(404);
        require __DIR__ . '/login/not-found.php';
        exit();
    }
}

function handle_login_request($route) {
    $login_routes = [
        '' => 'login.php',
        'forgot_password' => 'Forgot_password.php',
        'reset_password' => 'Reset_password.php',
        'logout' => 'logout.php'
    ];
    
    if (isset($login_routes[$route]) && file_exists(__DIR__ . '/login/' . $login_routes[$route])) {
        require __DIR__ . '/login/' . $login_routes[$route];
    } else {
        http_response_code(404);
        require __DIR__ . '/login/not-found.php';
        exit();
    }
}

function handle_api_request($endpoint) {
    $api_endpoints = [
        'user-count' => 'user-count.php'
    ];
    
    if (isset($api_endpoints[$endpoint]) && file_exists(__DIR__ . '/api/' . $api_endpoints[$endpoint])) {
        require __DIR__ . '/api/' . $api_endpoints[$endpoint];
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'API endpoint not found']);
        exit();
    }
}

// Main request handling
route_request();

// Fallback response if nothing else handles the request
http_response_code(404);
require __DIR__ . '/login/not-found.php';
?>