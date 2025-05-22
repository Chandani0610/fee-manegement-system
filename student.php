<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);
$log_file = __DIR__ . '/php_errors.log';
ini_set('log_errors', 1);
ini_set('error_log', $log_file);

require 'config.php';

if (!isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Unauthorized access to student.php');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare('SELECT unique_id, name, father_name, dob, semester, branch, course, batch, email FROM students WHERE unique_id = :unique_id');
        $stmt->execute(['unique_id' => $_SESSION['user_id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'profile' => $profile]);
    } catch (Exception $e) {
        error_log('Student GET error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    try {
        $unique_id = 'STU' . str_pad($pdo->query('SELECT COUNT(*) FROM students')->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('
            INSERT INTO students (unique_id, name, father_name, dob, semester, branch, course, batch, email, password)
            VALUES (:unique_id, :name, :father_name, :dob, :semester, :branch, :course, :batch, :email, :password)
        ');
        $stmt->execute([
            'unique_id' => $unique_id,
            'name' => $data['name'],
            'father_name' => $data['father_name'],
            'dob' => $data['dob'],
            'semester' => $data['semester'],
            'branch' => $data['branch'],
            'course' => $data['course'],
            'batch' => $data['batch'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT)
        ]);
        error_log('Student registered: unique_id=' . $unique_id);
        echo json_encode(['success' => true, 'unique_id' => $unique_id]);
    } catch (Exception $e) {
        error_log('Student registration error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error registering student']);
    }
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    try {
        $stmt = $pdo->prepare('
            UPDATE students
            SET name = :name, father_name = :father_name, dob = :dob, semester = :semester, branch = :branch, course = :course, batch = :batch
            WHERE unique_id = :unique_id
        ');
        $stmt->execute([
            'unique_id' => $_SESSION['user_id'],
            'name' => $data['name'],
            'father_name' => $data['father_name'],
            'dob' => $data['dob'],
            'semester' => $data['semester'],
            'branch' => $data['branch'],
            'course' => $data['course'],
            'batch' => $data['batch']
        ]);
        error_log('Profile updated: unique_id=' . $_SESSION['user_id']);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log('Profile update error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating profile']);
    }
}
?>