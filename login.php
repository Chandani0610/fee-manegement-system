<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);
$log_file = __DIR__ . '/php_errors.log';
ini_set('log_errors', 1);
ini_set('error_log', $log_file);


session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => false, 
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

require 'config.php';

if (isset($_GET['logout'])) {
    error_log('Logout requested: Destroying session, session_id=' . session_id());
    session_unset();
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $type = $data['type'] ?? '';

    error_log("Login attempt: type=$type, email=$email, session_id=" . session_id());

    if (empty($email) || empty($password)) {
        error_log("Login failed: Missing email or password");
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit;
    }

    if ($type === 'student') {
        try {
            $stmt = $pdo->prepare('SELECT unique_id, password FROM students WHERE email = :email');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            error_log("Student login query result: " . ($user ? 'Found, unique_id=' . $user['unique_id'] : 'Not found'));

            if (!$user) {
                error_log("Student login failed: No student found for email=$email");
                echo json_encode(['success' => false, 'message' => 'Invalid email']);
                exit;
            }

            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['unique_id'];
                $_SESSION['user_type'] = 'student';
                error_log("Student login successful: user_id={$user['unique_id']}, session_id=" . session_id());
                echo json_encode(['success' => true, 'user_id' => $user['unique_id']]);
            } else {
                error_log("Student login failed: Incorrect password for email=$email");
                echo json_encode(['success' => false, 'message' => 'Invalid password']);
            }
        } catch (Exception $e) {
            error_log('Student login error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } elseif ($type === 'admin') {
        try {
            $stmt = $pdo->prepare('SELECT id, password FROM admins WHERE username = :username');
            $stmt->execute(['username' => $email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            error_log("Admin login query result: " . ($admin ? 'Found, id=' . $admin['id'] : 'Not found'));

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['user_type'] = 'admin';
                error_log("Admin login successful: admin_id={$admin['id']}, session_id=" . session_id());
                echo json_encode(['success' => true, 'admin_id' => $admin['id']]);
            } else {
                error_log("Admin login failed: Invalid credentials for username=$email");
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
        } catch (Exception $e) {
            error_log('Admin login error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        error_log('Invalid login type: ' . $type);
        echo json_encode(['success' => false, 'message' => 'Invalid login type']);
    }
} else {
    error_log('Invalid request method: ' . $method);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>