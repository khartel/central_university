<?php
session_start();
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['lecturer', 'student'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

if ($action === 'courses' && $_SESSION['role'] === 'lecturer') {
    $stmt = $conn->prepare("
        SELECT c.course_code, c.course_name
        FROM course_assignments ca
        JOIN courses c ON ca.course_code = c.course_code
        WHERE ca.lecturer_id = ?
        ORDER BY c.course_code
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode([
        'success' => true,
        'courses' => $courses
    ]);
} elseif ($action === 'stats' && $_SESSION['role'] === 'lecturer') {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT ce.user_id) as total_students
        FROM course_enrollments ce
        JOIN course_assignments ca ON ce.course_code = ca.course_code
        WHERE ca.lecturer_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_students = $result->fetch_assoc()['total_students'];
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT COUNT(*) as today_classes
        FROM timetable t
        JOIN course_assignments ca ON t.course_code = ca.course_code
        WHERE ca.lecturer_id = ? AND t.day_of_week = DAYNAME(CURDATE())
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $today_classes = $result->fetch_assoc()['today_classes'];
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT AVG(attendance_percentage) as avg_attendance
        FROM (
            SELECT t.course_code, t.day_of_week, t.start_time, 
                   (COUNT(a.id) / (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_code = t.course_code)) * 100 as attendance_percentage
            FROM timetable t
            JOIN course_assignments ca ON t.course_code = ca.course_code
            LEFT JOIN attendance a ON t.course_code = a.course_code 
                AND DATE(a.attendance_date) = CURDATE()
            WHERE ca.lecturer_id = ?
            GROUP BY t.course_code, t.day_of_week, t.start_time
        ) as subquery
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $avg_attendance = round($result->fetch_assoc()['avg_attendance'] ?? 0, 1);
    $stmt->close();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_students' => $total_students,
            'today_classes' => $today_classes,
            'average_attendance' => $avg_attendance
        ]
    ]);
} elseif ($action === 'stats' && $_SESSION['role'] === 'student') {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT t.id) as total,
            COUNT(DISTINCT a.id) as attended,
            COUNT(DISTINCT t.id) - COUNT(DISTINCT a.id) as missed
        FROM timetable t
        JOIN course_enrollments ce ON t.course_code = ce.course_code
        LEFT JOIN attendance a ON t.course_code = a.course_code AND a.user_id = ce.user_id
        WHERE ce.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    
    // Ensure counts are non-negative
    $stats['total'] = max(0, $stats['total']);
    $stats['attended'] = max(0, $stats['attended']);
    $stats['missed'] = max(0, $stats['missed']);
    
    // Calculate rate, cap at 100%
    $stats['rate'] = $stats['total'] > 0 ? min(100, round(($stats['attended'] / $stats['total']) * 100, 1)) : 0;
    
    $stmt->close();

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
} elseif ($action === 'today' && $_SESSION['role'] === 'lecturer') {
    $stmt = $conn->prepare("
        SELECT t.course_code, c.course_name, 
               CONCAT(TIME_FORMAT(t.start_time, '%H:%i'), ' - ', TIME_FORMAT(t.end_time, '%H:%i')) as time,
               COUNT(a.id) as present_count,
               (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_code = t.course_code) - COUNT(a.id) as absent_count
        FROM timetable t
        JOIN course_assignments ca ON t.course_code = ca.course_code
        JOIN courses c ON t.course_code = c.course_code
        LEFT JOIN attendance a ON t.course_code = a.course_code 
            AND DATE(a.attendance_date) = CURDATE()
        WHERE ca.lecturer_id = ? AND t.day_of_week = DAYNAME(CURDATE())
        GROUP BY t.course_code, t.start_time, t.end_time
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $attendance = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode([
        'success' => true,
        'attendance' => $attendance
    ]);
} elseif ($action === 'today' && $_SESSION['role'] === 'student') {
    $stmt = $conn->prepare("
        SELECT c.course_code, c.course_name as name, 
               CONCAT(TIME_FORMAT(t.start_time, '%H:%i'), ' - ', TIME_FORMAT(t.end_time, '%H:%i')) as schedule,
               u.full_name as lecturer,
               EXISTS (
                   SELECT 1 FROM attendance a 
                   WHERE a.course_code = t.course_code 
                   AND a.user_id = ? 
                   AND DATE(a.attendance_date) = CURDATE()
               ) as attended
        FROM timetable t
        JOIN course_enrollments ce ON t.course_code = ce.course_code
        JOIN courses c ON t.course_code = c.course_code
        JOIN course_assignments ca ON t.course_code = ca.course_code
        JOIN users u ON ca.lecturer_id = u.id
        WHERE ce.user_id = ? AND t.day_of_week = DAYNAME(CURDATE())
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $classes = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode([
        'success' => true,
        'classes' => $classes
    ]);
} elseif ($action === 'history' && $_SESSION['role'] === 'student') {
    $stmt = $conn->prepare("
        SELECT c.course_name, a.attendance_date as date, a.status
        FROM attendance a
        JOIN courses c ON a.course_code = c.course_code
        WHERE a.user_id = ?
        ORDER BY a.attendance_date DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
} elseif ($action === 'details' && isset($_GET['course_code']) && $_SESSION['role'] === 'lecturer') {
    $course_code = $_GET['course_code'];
    $stmt = $conn->prepare("
        SELECT u.full_name, a.attendance_date, a.status, a.latitude, a.longitude
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE a.course_code = ?
        ORDER BY a.attendance_date DESC
    ");
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $details = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode([
        'success' => true,
        'details' => $details
    ]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'lecturer') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['action']) && $data['action'] === 'generate_code' && isset($data['course_code'])) {
        $course_code = $data['course_code'];

        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM course_assignments ca
            WHERE ca.lecturer_id = ? AND ca.course_code = ?
        ");
        $stmt->bind_param("is", $user_id, $course_code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->fetch_assoc()['count'] === 0) {
            echo json_encode(['success' => false, 'message' => 'Course not assigned to lecturer']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare("
            SELECT venue, latitude, longitude
            FROM timetable
            WHERE course_code = ? AND day_of_week = DAYNAME(CURDATE())
            LIMIT 1
        ");
        $stmt->bind_param("s", $course_code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No class scheduled for this course today']);
            $stmt->close();
            exit;
        }
        $timetable = $result->fetch_assoc();
        $stmt->close();

        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }

        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $stmt = $conn->prepare("
            INSERT INTO attendance_codes (course_code, code, venue, latitude, longitude, expires_at, lecturer_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssddsi", $course_code, $code, $timetable['venue'], $timetable['latitude'], $timetable['longitude'], $expires_at, $user_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'code' => $code
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'student') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['action']) && $data['action'] === 'submit_attendance' && isset($data['code']) && isset($data['latitude']) && isset($data['longitude'])) {
        $code = $data['code'];
        $student_lat = $data['latitude'];
        $student_lon = $data['longitude'];

        $stmt = $conn->prepare("
            SELECT course_code, venue, latitude, longitude
            FROM attendance_codes
            WHERE code = ? AND expires_at > NOW()
        ");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired code']);
            $stmt->close();
            exit;
        }
        $code_data = $result->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM course_enrollments
            WHERE user_id = ? AND course_code = ?
        ");
        $stmt->bind_param("is", $user_id, $code_data['course_code']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->fetch_assoc()['count'] === 0) {
            echo json_encode(['success' => false, 'message' => 'Not enrolled in this course']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        $distance = haversineDistance($student_lat, $student_lon, $code_data['latitude'], $code_data['longitude']);
        if ($distance > 1000) {
            echo json_encode(['success' => false, 'message' => 'You are not within the class location']);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO attendance (user_id, course_code, latitude, longitude)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = 'present', latitude = ?, longitude = ?
        ");
        $stmt->bind_param("isdddd", $user_id, $code_data['course_code'], $student_lat, $student_lon, $student_lat, $student_lon);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Attendance recorded']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>