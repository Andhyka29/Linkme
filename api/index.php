<?php
declare(strict_types=1);

session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($request_uri, PHP_URL_PATH);
$path = rtrim($path, '/');
$path = $path ?: '/';

$routes = [
    '/api/links'     => ['file' => __DIR__ . '/links.php',      'method' => ['GET', 'POST', 'PUT', 'DELETE']],
    '/api/login'     => ['file' => __DIR__ . '/login.php',      'method' => ['POST']],
    '/api/register'  => ['file' => __DIR__ . '/register.php',   'method' => ['POST']],
    '/api/logout'    => ['file' => __DIR__ . '/logout.php',      'method' => ['POST']],
    '/api/user'      => ['file' => __DIR__ . '/user.php',        'method' => ['GET']],
    '/login'        => ['file' => null,                         'public' => true],
    '/register'     => ['file' => null,                         'public' => true],
    '/dashboard'    => ['file' => null,                         'auth' => true],
    '/'             => ['file' => null,                         'public' => true],
];

$matched = false;

foreach ($routes as $route => $config) {
    if ($path === $route || preg_match('#^' . $route . '(?:/.*)?$#', $path)) {
        $matched = true;
        
        if (isset($config['auth']) && $config['auth'] && !is_logged_in()) {
            header('Location: /login');
            exit;
        }
        
        if (isset($config['public']) && $config['public']) {
            if ($path === '/' || $path === '') {
                if (is_logged_in()) {
                    header('Location: /dashboard');
                } else {
                    header('Location: /login');
                }
                exit;
            }
            $html = file_get_contents(__DIR__ . '/../public/index.html');
            echo $html;
            exit;
        }
        
        if ($config['file'] && in_array($request_method, $config['method'])) {
            require_once $config['file'];
            exit;
        }
        
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
}

if (!$matched) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found']);
}