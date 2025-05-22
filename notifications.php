<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);
$log_file = __DIR__ . '/php_errors.log';
ini_set('log_errors', 1);
ini_set('error_log', $log_file);

require 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
error_log("Notifications.php accessed: method=$method, session_id=" . session_id() . ', session_data=' . json_encode($_SESSION));

if ($method === 'GET' || $method === 'PUT') {
    if (!isset($_SESSION['user_id'])) {
        error_log('Unauthorized access to notifications: student action, no user_id');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    $student_id = $_SESSION['user_id'];
} elseif ($method === 'POST') {
    if (!isset($_SESSION['admin_id'])) {
        error_log('Unauthorized access to notifications: admin broadcast, no admin_id');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    $student_id = null; 
} else {
    error_log("Invalid method: $method");
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if ($method === 'GET') {
    try {
        $notifications = $pdo->prepare('
            SELECT n.id, n.type, n.message, n.created_at, n.is_read
            FROM notifications n
            WHERE n.student_id = :student_id
            ORDER BY n.created_at DESC
        ');
        $notifications->execute(['student_id' => $student_id]);
        $notifications_data = $notifications->fetchAll(PDO::FETCH_ASSOC);

        $unread_count_query = $pdo->prepare('
            SELECT COUNT(*) as unread_count
            FROM notifications
            WHERE student_id = :student_id AND is_read = FALSE
        ');
        $unread_count_query->execute(['student_id' => $student_id]);
        $unread_count = $unread_count_query->fetch(PDO::FETCH_ASSOC)['unread_count'];

        error_log("Notifications fetched: count=" . count($notifications_data) . ", unread_count=$unread_count, student_id=$student_id");
        echo json_encode([
            'success' => true,
            'notifications' => $notifications_data,
            'unread_count' => (int)$unread_count
        ]);
    } catch (Exception $e) {
        error_log('Notifications GET error: ' . $e->getMessage() . ', student_id=' . $student_id);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $filter = $data['filter'] ?? 'all';
    $value = $data['value'] ?? '';
    $message = trim($data['message'] ?? '');
    $type = $data['type'] ?? 'Info';

    error_log("Broadcast notification attempt: filter=$filter, value=$value, message_length=" . strlen($message) . ", type=$type, admin_id=" . $_SESSION['admin_id']);

    if (empty($message)) {
        error_log('Broadcast failed: Empty message');
        echo json_encode(['success' => false, 'message' => 'Message is required']);
        exit;
    }

    if (!in_array($filter, ['all', 'semester', 'branch', 'course', 'batch'])) {
        error_log("Broadcast failed: Invalid filter=$filter");
        echo json_encode(['success' => false, 'message' => 'Invalid filter']);
        exit;
    }

    try {
        $query = 'SELECT unique_id FROM students';
        $params = [];
        if ($filter !== 'all') {
            $query .= " WHERE $filter = :value";
            $params['value'] = $value;
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($students)) {
            error_log("Broadcast failed: No students found for filter=$filter, value=$value");
            echo json_encode(['success' => false, 'message' => 'No students found']);
            exit;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('
            INSERT INTO notifications (student_id, type, message, created_at, is_read)
            VALUES (:student_id, :type, :message, NOW(), FALSE)
        ');
        $affected = 0;
        foreach ($students as $student) {
            $stmt->execute([
                'student_id' => $student['unique_id'],
                'type' => $type,
                'message' => $message
            ]);
            $affected++;
        }
        $pdo->commit();
        error_log("Broadcast notifications sent: count=$affected, filter=$filter, value=$value, admin_id=" . $_SESSION['admin_id']);
        echo json_encode(['success' => true, 'affected' => $affected]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Broadcast POST error: ' . $e->getMessage() . ', admin_id=' . $_SESSION['admin_id']);
        echo json_encode(['success' => false, 'message' => 'Error sending notification: ' . $e->getMessage()]);
    }
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $notification_id = $data['notification_id'] ?? 0;

    error_log("Marking notification read: notification_id=$notification_id, student_id=$student_id");

    try {
        $stmt = $pdo->prepare('
            UPDATE notifications
            SET is_read = TRUE
            WHERE id = :id AND student_id = :student_id
        ');
        $stmt->execute([
            'id' => $notification_id,
            'student_id' => $student_id
        ]);
        error_log("Notification marked as read: notification_id=$notification_id, student_id=$student_id");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log('Notification PUT error: ' . $e->getMessage() . ', student_id=' . $student_id);
        echo json_encode(['success' => false, 'message' => 'Error updating notification']);
    }
}
?>