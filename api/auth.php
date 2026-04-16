<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in(): bool
{
    start_session();
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /login');
        exit;
    }
}

function get_current_user_id(): ?int
{
    start_session();
    return $_SESSION['user_id'] ?? null;
}

function get_current_username(): ?string
{
    start_session();
    return $_SESSION['username'] ?? null;
}

function login_user(int $user_id, string $username): void
{
    start_session();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    session_destroy();
}

function register_user(string $username, string $password): array
{
    $pdo = getPDO();
    
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username already taken'];
    }
    
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
    $stmt->execute([$username, $hashed_password]);
    
    return [
        'success' => true,
        'user_id' => (int) $pdo->lastInsertId(),
        'message' => 'Registration successful'
    ];
}

function verify_login(string $username, string $password): array
{
    $pdo = getPDO();
    
    $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    return [
        'success' => true,
        'user_id' => (int) $user['id'],
        'username' => $user['username'],
        'message' => 'Login successful'
    ];
}