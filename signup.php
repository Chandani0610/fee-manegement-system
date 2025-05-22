<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $type = filter_var($data['type'], FILTER_SANITIZE_STRING);

    if ($type === 'student') {
        $name = filter_var($data['name'], FILTER_SANITIZE_STRING);
        $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $class = filter_var($data['class'], FILTER_SANITIZE_STRING);
        $branch = filter_var($data['branch'], FILTER_SANITIZE_STRING);
        $department = filter_var($data['department'], FILTER_SANITIZE_STRING);
        $batch = filter_var($data['batch'], FILTER_SANITIZE_STRING);

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO students (name, email, password, class, branch, department, batch) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $class, $branch, $department, $batch]);
            error_log('Student signup: email=' . $email);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log('Student signup error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } elseif ($type === 'admin') {
        $username = filter_var($data['username'], FILTER_SANITIZE_STRING);
        $password = password_hash($data['password'], PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Username already exists']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $password]);
            error_log('Admin signup: username=' . $username);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            error_log('Admin signup error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
    }
}
?>