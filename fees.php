<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);
$log_file = __DIR__ . '/php_errors.log';
ini_set('log_errors', 1);
ini_set('error_log', $log_file);

require 'config.php';
require 'vendor/autoload.php';

use Razorpay\Api\Api;

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    error_log('Unauthorized access to fees.php');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    try {
        if ($action === 'history') {
            if (isset($_SESSION['admin_id']) && isset($_GET['student_id'])) {
                $stmt = $pdo->prepare('
                    SELECT f.id, s.name AS student_name, f.fee_type, f.amount, f.due_date, f.paid_date, f.status
                    FROM fees f
                    JOIN students s ON f.student_id = s.unique_id
                    WHERE f.student_id = :student_id
                    ORDER BY f.due_date DESC
                ');
                $stmt->execute(['student_id' => $_GET['student_id']]);
            } elseif (isset($_SESSION['user_id'])) {
                $stmt = $pdo->prepare('
                    SELECT f.id, f.fee_type, f.amount, f.due_date, f.paid_date, f.status
                    FROM fees f
                    WHERE f.student_id = :student_id
                    ORDER BY f.due_date DESC
                ');
                $stmt->execute(['student_id' => $_SESSION['user_id']]);
            } else {
                $stmt = $pdo->query('
                    SELECT f.id, s.name AS student_name, f.fee_type, f.amount, f.due_date, f.paid_date, f.status
                    FROM fees f
                    JOIN students s ON f.student_id = s.unique_id
                    ORDER BY f.due_date DESC
                ');
            }
            $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log('Fees history fetched: ' . count($fees));
            echo json_encode(['success' => true, 'fees' => $fees]);
        } else {
            $stmt = $pdo->prepare('
                SELECT id, fee_type, amount, due_date, status, student_id
                FROM fees
                WHERE student_id = :student_id AND status = "Unpaid"
            ');
            $stmt->execute(['student_id' => $_SESSION['user_id']]);
            $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log('Fees fetched: ' . count($fees));
            echo json_encode(['success' => true, 'fees' => $fees]);
        }
    } catch (Exception $e) {
        error_log('Fees GET error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $fee_id = $data['fee_id'] ?? 0;
    $payment_id = $data['payment_id'] ?? '';

    if (!$fee_id || !$payment_id) {
        error_log('Invalid payment data: fee_id or payment_id missing');
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    try {
        $api = new Api('rzp_test_icdVAXazudEtAP', 'DyosBc23oYxnJ1F5SvcI4f9g');
        $payment = $api->payment->fetch($payment_id);

        if ($payment->status === 'captured') {
            $stmt = $pdo->prepare('
                UPDATE fees
                SET status = "Paid", paid_date = NOW()
                WHERE id = :fee_id AND student_id = :student_id
            ');
            $stmt->execute(['fee_id' => $fee_id, 'student_id' => $_SESSION['user_id']]);
            error_log("Payment verified for fee_id: $fee_id");
            echo json_encode(['success' => true]);
        } else {
            error_log("Payment not captured for payment_id: $payment_id");
            echo json_encode(['success' => false, 'message' => 'Payment not verified']);
        }
    } catch (Exception $e) {
        error_log('Payment verification error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Payment error']);
    }
}
?>