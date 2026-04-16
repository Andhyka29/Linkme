<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

logout_user();

echo json_encode(['success' => true, 'message' => 'Logged out successfully']);