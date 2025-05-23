<?php
session_start();
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'get_notifications') {
    try {
        // Fetch missed classes from previous day
        $stmt = $conn->prepare("
            SELECT DISTINCT c.course_code, c.course_name, 
                   CONCAT(t.day_of_week, ' ', TIME_FORMAT(t.start_time, '%H:%i'), ' - ', TIME_FORMAT(t.end_time, '%H:%i')) as schedule,
                   u.full_name as lecturer,
                   t.venue
            FROM timetable t
            JOIN course_enrollments ce ON t.course_code = ce.course_code
            JOIN courses c ON t.course_code = c.course_code
            JOIN course_assignments ca ON t.course_code = ca.course_code
            JOIN users u ON ca.lecturer_id = u.id
            LEFT JOIN attendance a ON t.course_code = a.course_code 
                AND a.user_id = ce.user_id 
                AND DATE(a.attendance_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            WHERE ce.user_id = ? 
                AND t.day_of_week = DAYNAME(DATE_SUB(CURDATE(), INTERVAL 1 DAY))
                AND a.id IS NULL
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $missed_classes_previous_day = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Fetch attended classes (today's classes where attendance is marked as present)
        $stmt = $conn->prepare("
            SELECT c.course_code, c.course_name, 
                   CONCAT(t.day_of_week, ' ', TIME_FORMAT(t.start_time, '%H:%i'), ' - ', TIME_FORMAT(t.end_time, '%H:%i')) as schedule,
                   u.full_name as lecturer,
                   t.venue
            FROM attendance a
            JOIN courses c ON a.course_code = c.course_code
            JOIN timetable t ON a.course_code = t.course_code
            JOIN course_assignments ca ON t.course_code = ca.course_code
            JOIN users u ON ca.lecturer_id = u.id
            WHERE a.user_id = ? 
                AND a.status = 'present'
                AND DATE(a.attendance_date) = CURDATE()
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $attended_classes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Fetch upcoming classes (today only, after current time)
        $stmt = $conn->prepare("
            SELECT c.course_code, c.course_name, 
                   CONCAT(t.day_of_week, ' ', TIME_FORMAT(t.start_time, '%H:%i'), ' - ', TIME_FORMAT(t.end_time, '%H:%i')) as schedule,
                   u.full_name as lecturer,
                   t.venue
            FROM timetable t
            JOIN course_enrollments ce ON t.course_code = ce.course_code
            JOIN courses c ON t.course_code = c.course_code
            JOIN course_assignments ca ON t.course_code = ca.course_code
            JOIN users u ON ca.lecturer_id = u.id
            WHERE ce.user_id = ? 
                AND t.day_of_week = DAYNAME(CURDATE())
                AND t.start_time > CURTIME()
            ORDER BY t.start_time
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $upcoming_classes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Fetch attendance marked notifications (clear if from previous day)
        $stmt = $conn->prepare("
            SELECT course_code, message
            FROM temp_notifications
            WHERE user_id = ? 
                AND type = 'attendance_marked'
                AND created_at = CURDATE()
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance_marked = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Clear old attendance marked notifications
        $stmt = $conn->prepare("
            DELETE FROM temp_notifications
            WHERE user_id = ? 
                AND type = 'attendance_marked'
                AND created_at < CURDATE()
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'notifications' => [
                'missed_classes_previous_day' => $missed_classes_previous_day,
                'attended_classes' => $attended_classes,
                'upcoming_classes' => $upcoming_classes,
                'attendance_marked' => $attendance_marked
            ]
        ]);
    } catch (Exception $e) {
        file_put_contents('error.log', 'Notifications Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    }
} elseif ($action === 'add_attendance_notification') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $course_code = $data['course_code'] ?? '';
        $message = $data['message'] ?? '';

        if (!$course_code || !$message) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO temp_notifications (user_id, type, course_code, message, created_at)
            VALUES (?, 'attendance_marked', ?, ?, CURDATE())
        ");
        $stmt->bind_param("iss", $user_id, $course_code, $message);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Notification added']);
    } catch (Exception $e) {
        file_put_contents('error.log', 'Add Notification Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>