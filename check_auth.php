<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);
$log_file = __DIR__ . '/php_errors.log';
ini_set('log_errors', 1);
ini_set('error_log', $log_file);

if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) {
    error_log('Auth check successful: session_id=' . session_id() . ', user_id=' . ($_SESSION['user_id'] ?? $_SESSION['admin_id']));
    echo json_encode(['success' => true]);
} else {
    error_log('Auth check failed: session_id=' . session_id() . ', session_data=' . json_encode($_SESSION));
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
}
?>