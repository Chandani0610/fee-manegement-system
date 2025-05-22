<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Ensure the admin is logged in
        if (!isset($_SESSION['admin_id'])) {
            error_log('Admin dashboard access denied: No admin session');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        // Dashboard overview data
        $overview = [
            'total_students' => $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn() ?: 0,
            'total_payments' => $pdo->query('SELECT SUM(amount) FROM fees WHERE status = "Paid"')->fetchColumn() ?: 0,
            'unpaid_fees' => $pdo->query('SELECT COUNT(*) FROM fees WHERE status = "Pending"')->fetchColumn() ?: 0
        ];

        // Students
        $students = $pdo->query('
            SELECT unique_id, name, email, semester, branch, course, batch 
            FROM students
        ')->fetchAll(PDO::FETCH_ASSOC);

        // Payments
        $payments = $pdo->query('
            SELECT s.name, f.fee_type, f.amount, f.due_date, f.paid_date, f.status
            FROM fees f
            JOIN students s ON f.student_id = s.unique_id
        ')->fetchAll(PDO::FETCH_ASSOC);

        // Tokens
        $tokens = $pdo->query('
            SELECT t.id, s.name, t.token, t.slot_date, t.slot_time, t.created_at, t.expiry_date, t.status
            FROM tokens t
            JOIN fees f ON t.fee_id = f.id
            JOIN students s ON f.student_id = s.unique_id
        ')->fetchAll(PDO::FETCH_ASSOC);

        // Filters
        $filters = [
            'semesters' => $pdo->query('SELECT DISTINCT semester FROM students ORDER BY semester')->fetchAll(PDO::FETCH_COLUMN) ?: [],
            'branches' => $pdo->query('SELECT DISTINCT branch FROM students ORDER BY branch')->fetchAll(PDO::FETCH_COLUMN) ?: [],
            'courses' => $pdo->query('SELECT DISTINCT course FROM students ORDER BY course')->fetchAll(PDO::FETCH_COLUMN) ?: [],
            'batches' => $pdo->query('SELECT DISTINCT batch FROM students ORDER BY batch')->fetchAll(PDO::FETCH_COLUMN) ?: []
        ];

        error_log('Admin dashboard data fetched successfully');
        echo json_encode([
            'success' => true,
            'overview' => $overview,
            'students' => $students,
            'payments' => $payments,
            'tokens' => $tokens,
            'filters' => $filters
        ]);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        // Admin signup
        if ($action === 'admin_signup') {
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';

            if (!$username || !$password) {
                echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
                exit;
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Username already exists.']);
                exit;
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
            $stmt->execute([$username, $hashedPassword]);

            error_log("New admin registered: $username");
            echo json_encode(['success' => true, 'message' => 'Admin registered successfully']);
        }

        // Add student
        elseif ($action === 'add_student') {
            $stmt = $pdo->prepare('
                INSERT INTO students (unique_id, name, father_name, dob, semester, branch, course, batch, email, password)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $unique_id = 'STU' . str_pad($pdo->query('SELECT COUNT(*) FROM students')->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $unique_id,
                $data['name'],
                $data['father_name'],
                $data['dob'],
                $data['semester'],
                $data['branch'],
                $data['course'],
                $data['batch'],
                $data['email'],
                $password
            ]);
            error_log("Student added: unique_id=$unique_id");
            echo json_encode(['success' => true, 'unique_id' => $unique_id]);
        }

        // Assign fees
        elseif ($action === 'assign_fees') {
            $filter = $data['filter'];
            $value = $data['value'];
            $where = $filter === 'all' ? '' : "WHERE $filter = ?";
            $params = $filter === 'all' ? [] : [$value];

            $stmt = $pdo->prepare("SELECT unique_id FROM students $where");
            $stmt->execute($params);
            $student_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $pdo->prepare('
                INSERT INTO fees (student_id, fee_type, amount, due_date, status)
                VALUES (?, ?, ?, ?, "Unpaid")
            ');
            $affected = 0;
            foreach ($student_ids as $student_id) {
                $stmt->execute([
                    $student_id,
                    $data['fee_type'],
                    $data['amount'],
                    $data['due_date']
                ]);
                $affected++;
            }
            error_log("Fees assigned: affected=$affected");
            echo json_encode(['success' => true, 'affected' => $affected]);
        }

        // Edit fee
        elseif ($action === 'edit_fee') {
            $stmt = $pdo->prepare('
                UPDATE fees
                SET fee_type = ?, amount = ?, due_date = ?, status = ?, paid_date = ?
                WHERE id = ? AND student_id = ?
            ');
            $stmt->execute([
                $data['fee_type'],
                $data['amount'],
                $data['due_date'],
                $data['status'],
                $data['paid_date'],
                $data['fee_id'],
                $data['student_id']
            ]);
            error_log("Fee updated: fee_id={$data['fee_id']}");
            echo json_encode(['success' => true]);
        }

        else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
} catch (Exception $e) {
    error_log('Admin error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
