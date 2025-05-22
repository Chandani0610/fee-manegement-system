<?php
$host = 'localhost';
$dbname = 'collegesync_fees';
$username = 'root';
$password = '';

$log_file = __DIR__ . '/php_errors.log';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log('Database connection successful');
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}
?>