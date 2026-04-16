<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$csrf_token = $input['csrf_token'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

if (!verify_csrf($csrf_token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
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
    'username' => $result['username']
]);