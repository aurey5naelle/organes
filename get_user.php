<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo json_encode(['logged_in' => false]);
    exit();
}

echo json_encode([
    'logged_in' => true,
    'user_id'   => $_SESSION['user_id'] ?? $_SESSION['user_email'] ?? 'DONNEUR_DEMO',
    'user_name' => $_SESSION['user_name'] ?? ''
]);
exit();
?>