<?php
declare(strict_types=1);

// ============================================================
// SINGLE ROUTER - LinkHub API
// ============================================================

// Start session FIRST before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// CORS & HEADERS - Handle OPTIONS preflight
// ============================================================
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = rtrim($path, '/');
$path = $path ?: '/';

// Set headers EARLY (before any output)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Accept');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Handle preflight - return early with 200
if ($requestMethod === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================
// ROUTE DEFINITIONS
// ============================================================
$routes = [
    // API endpoints (method => handler)
    '/api/links'   => ['GET', 'POST', 'PUT', 'DELETE'],
    '/api/login'  => ['POST'],
    '/api/register' => ['POST'],
    '/api/logout' => ['POST'],
    '/api/user'   => ['GET'],
    '/api/csrf'  => ['GET'],  // New: endpoint to get CSRF token
];

// Find matching route
$matchedRoute = null;
$allowedMethods = [];

foreach ($routes as $route => $methods) {
    // Exact match or /api/links/123 style match
    if ($path === $route || preg_match('#^' . preg_quote($route, '#') . '(?:/.*)?$#', $path)) {
        $matchedRoute = $route;
        $allowedMethods = $methods;
        break;
    }
}

// Route not found
if ($matchedRoute === null) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Endpoint not found'
    ]);
    exit;
}

// ============================================================
// METHOD CHECK
// ============================================================
if (!in_array($requestMethod, $allowedMethods)) {
    http_response_code(405);
    header('Allow: ' . implode(', ', $allowedMethods));
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// ============================================================
// LOAD DEPENDENCIES
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

initDatabase();

// ============================================================
// ROUTE HANDLER - Single entry point
// ============================================================
try {
    switch ($matchedRoute) {
        case '/api/csrf':
            // Return CSRF token (no auth required)
            echo json_encode([
                'success' => true,
                'csrf_token' => csrf_token()
            ]);
            break;

        case '/api/user':
            handleUser();
            break;

        case '/api/login':
            handleLogin();
            break;

        case '/api/register':
            handleRegister();
            break;

        case '/api/logout':
            handleLogout();
            break;

        case '/api/links':
            handleLinks();
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Endpoint not found'
            ]);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}

// ============================================================
// HANDLER FUNCTIONS (inline to avoid separate files issues)
// ============================================================

function handleUser(): void
{
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'user_id' => get_current_user_id(),
        'username' => get_current_username()
    ]);
}

function handleLogin(): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }

    $result = verify_login($username, $password);

    if (!$result['success']) {
        http_response_code(401);
        echo json_encode($result);
        exit;
    }

    login_user($result['user_id'], $result['username']);

    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'user_id' => $result['user_id'],
        'username' => $result['username']
    ]);
}

function handleRegister(): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }

    if (strlen($username) < 3 || strlen($username) > 50) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username must be between 3 and 50 characters']);
        exit;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }

    if ($password !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }

    $result = register_user($username, $password);

    if (!$result['success']) {
        http_response_code(400);
        echo json_encode($result);
        exit;
    }

    login_user($result['user_id'], $username);

    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'user_id' => $result['user_id'],
        'username' => $username
    ]);
}

function handleLogout(): void
{
    logout_user();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

function handleLinks(): void
{
    $user_id = get_current_user_id();

    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            getLinks($user_id);
            break;
        case 'POST':
            createLink($user_id);
            break;
        case 'PUT':
            updateLink($user_id);
            break;
        case 'DELETE':
            deleteLink($user_id);
            break;
    }
}

function getLinks(int $user_id): void
{
    $pdo = getPDO();

    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';

    $sql = 'SELECT id, title, url, category, created_at FROM links WHERE user_id = ?';
    $params = [$user_id];

    if (!empty($search)) {
        $sql .= ' AND (title LIKE ? OR url LIKE ?)';
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($category)) {
        $sql .= ' AND category = ?';
        $params[] = $category;
    }

    $sql .= ' ORDER BY created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $links = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'links' => $links
    ]);
}

function createLink(int $user_id): void
{
    $pdo = getPDO();

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $title = trim($input['title'] ?? '');
    $url = trim($input['url'] ?? '');
    $category = trim($input['category'] ?? '');

    if (empty($title) || empty($url)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title and URL are required']);
        exit;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO links (user_id, title, url, category) VALUES (?, ?, ?, ?)');
    $stmt->execute([$user_id, $title, $url, $category ?: null]);

    echo json_encode([
        'success' => true,
        'message' => 'Link created successfully',
        'link_id' => (int) $pdo->lastInsertId()
    ]);
}

function updateLink(int $user_id): void
{
    $pdo = getPDO();

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $link_id = (int) ($input['id'] ?? 0);
    $title = trim($input['title'] ?? '');
    $url = trim($input['url'] ?? '');
    $category = trim($input['category'] ?? '');

    if (!$link_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Link ID is required']);
        exit;
    }

    if (empty($title) || empty($url)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title and URL are required']);
        exit;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE links SET title = ?, url = ?, category = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$title, $url, $category ?: null, $link_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Link not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Link updated successfully'
    ]);
}

function deleteLink(int $user_id): void
{
    $pdo = getPDO();

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $link_id = (int) ($input['id'] ?? 0);

    if (!$link_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Link ID is required']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM links WHERE id = ? AND user_id = ?');
    $stmt->execute([$link_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Link not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Link deleted successfully'
    ]);
}






