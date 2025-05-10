<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Paths
define('BASE_PATH', __DIR__);
define('ADMIN_PATH', BASE_PATH . '/admin');
define('USER_PATH', BASE_PATH . '/user');
define('LOGIN_PATH', BASE_PATH . '/login');
define('API_PATH', BASE_PATH . '/api');

// Secure session
session_start([
    'name' => 'SecureSessionID',
    'cookie_lifetime' => 14400,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Authentication check
function is_authenticated() {
    return isset($_SESSION['user_id']);
}

function get_user_role() {
    return $_SESSION['role'] ?? null;
}

function redirect($path) {
    header("Location: $path");
    exit();
}

function route_request() {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = trim($uri, '/');

    // Allow static files
    if (preg_match('/\.(css|js|jpg|jpeg|png|gif|ico|pdf|woff2?|ttf|svg)$/i', $path)) return false;

    // Route: Admin
    if (strpos($path, 'admin/') === 0) {
        if (!is_authenticated() || get_user_role() !== 'Admin') {
            redirect('/login/login.php');
        }
        $file = ADMIN_PATH . '/' . basename($path);
        return file_exists($file) ? require $file : show_404();
    }

    // Route: User
    if (strpos($path, 'user/') === 0) {
        if (!is_authenticated() || get_user_role() !== 'User') {
            redirect('/login/login.php');
        }
        $file = USER_PATH . '/' . basename($path);
        return file_exists($file) ? require $file : show_404();
    }

    // Route: Login
    if (strpos($path, 'login/') === 0) {
        $file = LOGIN_PATH . '/' . basename($path);
        return file_exists($file) ? require $file : show_404();
    }

    // Route: API
    if (strpos($path, 'api/') === 0) {
        $file = API_PATH . '/' . basename($path);
        return file_exists($file) ? require $file : show_404();
    }

    // Default: homepage
    if ($path === '' || $path === 'index.php') {
        if (is_authenticated()) {
            switch (get_user_role()) {
                case 'Admin': return redirect('/admin/adminDashboard.php');
                case 'User': return redirect('/user/Userdashboard.php');
            }
        } else {
            return redirect('/login/login.php');
        }
    }

    return show_404();
}

function show_404() {
    http_response_code(404);
    include LOGIN_PATH . '/not-found.php';
    exit();
}

route_request();
