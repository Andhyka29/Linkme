<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../csrf.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user_id = get_current_user_id();

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

switch ($method) {
    case 'GET':
        get_links($user_id);
        break;
    case 'POST':
        create_link($user_id);
        break;
    case 'PUT':
        update_link($user_id);
        break;
    case 'DELETE':
        delete_link($user_id);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function get_links(int $user_id): void
{
    $pdo = getPDO();
    
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    
    $sql = 'SELECT id, title, url, category, created_at FROM links WHERE user_id = ?';
    $params = [$user_id];
    
    if (!empty($search)) {
        $sql .= ' AND (title LIKE ? OR url LIKE ?)';
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
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

function create_link(int $user_id): void
{
    $pdo = getPDO();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $title = trim($input['title'] ?? '');
    $url = trim($input['url'] ?? '');
    $category = trim($input['category'] ?? '');
    $csrf_token = $input['csrf_token'] ?? '';
    
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
    
    if (!verify_csrf($csrf_token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
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

function update_link(int $user_id): void
{
    $pdo = getPDO();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $link_id = (int) ($input['id'] ?? 0);
    $title = trim($input['title'] ?? '');
    $url = trim($input['url'] ?? '');
    $category = trim($input['category'] ?? '');
    $csrf_token = $input['csrf_token'] ?? '';
    
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
    
    if (!verify_csrf($csrf_token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
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

function delete_link(int $user_id): void
{
    $pdo = getPDO();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $link_id = (int) ($input['id'] ?? 0);
    $csrf_token = $input['csrf_token'] ?? '';
    
    if (!$link_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Link ID is required']);
        exit;
    }
    
    if (!verify_csrf($csrf_token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
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