<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

error_log('Tokens.php accessed: method=' . $_SERVER['REQUEST_METHOD'] . ', session_id=' . session_id());

if (!isset($_SESSION['user_id'])) {
    error_log('Token generation denied: No user session');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $fee_id = $data['fee_id'] ?? null;
    error_log('Token generation attempt: fee_id=' . ($fee_id ?? 'null') . ', student_id=' . $_SESSION['user_id']);

    if (!$fee_id) {
        error_log('Token generation failed: Missing fee_id');
        echo json_encode(['success' => false, 'message' => 'Fee ID is required']);
        exit;
    }

    try {
        // Case-insensitive status check
        $stmt = $pdo->prepare('SELECT * FROM fees WHERE id = ? AND student_id = ? AND LOWER(status) = "Unpaid"');
        $stmt->execute([$fee_id, $_SESSION['user_id']]);
        $fee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$fee) {
            error_log('Token generation failed: No pending fee found for fee_id=' . $fee_id . ', student_id=' . $_SESSION['user_id']);
            echo json_encode(['success' => false, 'message' => 'Invalid or already paid fee']);
            exit;
        }

        error_log('Valid fee found: fee_id=' . $fee_id . ', fee_type=' . $fee['fee_type']);

        // Select available time slot
        $stmt = $pdo->prepare('
            SELECT id, slot_date, slot_time
            FROM time_slots
            WHERE slot_date >= DATE(NOW() + INTERVAL 1 DAY)
            AND used < capacity
            AND DAYOFWEEK(slot_date) NOT IN (1, 7)
            ORDER BY slot_date, slot_time
            LIMIT 1
        ');
        $stmt->execute();
        $slot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$slot) {
            error_log('Token generation failed: No available time slots');
            echo json_encode(['success' => false, 'message' => 'No available time slots']);
            exit;
        }

        error_log('Time slot selected: slot_id=' . $slot['id'] . ', date=' . $slot['slot_date'] . ', time=' . $slot['slot_time']);

        // Generate token
        $token = bin2hex(random_bytes(4));
        $expiry_date = date('Y-m-d H:i:s', strtotime('+7 days'));
        $stmt = $pdo->prepare('
            INSERT INTO tokens (fee_id, student_id,token, created_at, expiry_date, status, slot_date, slot_time)
            VALUES (?, ?, ?, NOW(), ?, "Unpaid", ?, ?)
        ');
        $stmt->execute([$fee_id, $_SESSION['user_id'], $token, $expiry_date, $slot['slot_date'], $slot['slot_time']]);

        // Update slot usage
        $stmt = $pdo->prepare('UPDATE time_slots SET used = used + 1 WHERE id = ?');
        $stmt->execute([$slot['id']]);

        error_log('Token generated: token=' . $token . ', fee_id=' . $fee_id);
        echo json_encode([
            'success' => true,
            'token' => $token,
            'slot_date' => $slot['slot_date'],
            'slot_time' => $slot['slot_time'],
            'expiry_date' => $expiry_date
        ]);
    } catch (Exception $e) {
        error_log('Token generation error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
} elseif ($method === 'PUT') {
    if (!isset($_SESSION['admin_id'])) {
        error_log('Token update denied: No admin session');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $token_id = $data['id'] ?? null;
    $status = $data['status'] ?? null;
    error_log('Token update attempt: token_id=' . ($token_id ?? 'null') . ', status=' . ($status ?? 'null'));

    if (!$token_id || !$status || !in_array($status, ['Approved', 'Rejected'])) {
        error_log('Token update failed: Invalid input');
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('UPDATE tokens SET status = ? WHERE id = ?');
        $stmt->execute([$status, $token_id]);
        if ($stmt->rowCount() === 0) {
            error_log('Token update failed: Token not found, token_id=' . $token_id);
            echo json_encode(['success' => false, 'message' => 'Token not found']);
            exit;
        }

        if ($status === 'Approved') {
            $stmt = $pdo->prepare('
                UPDATE fees f
                JOIN tokens t ON f.id = t.fee_id
                SET f.status = "Paid", f.paid_date = NOW()
                WHERE t.id = ?
            ');
            $stmt->execute([$token_id]);
            error_log('Fee marked as paid for token_id=' . $token_id);
        }

        error_log('Token updated: token_id=' . $token_id . ', status=' . $status);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log('Token update error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
} else {
    error_log('Invalid request method: ' . $method);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>