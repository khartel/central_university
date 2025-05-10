<?php
session_start();
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['lecturer', 'student'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Determine action based on request method
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = isset($data['action']) ? $data['action'] : '';
} else {
    $action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
}

// Function to calculate distance between two points (in kilometers)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon/2) * sin($dLon/2);
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
    try {
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

        // Log the results for debugging
        file_put_contents('debug.log', print_r(['today_attendance' => $attendance, 'user_id' => $user_id, 'day' => date('l')], true), FILE_APPEND);

        echo json_encode([
            'success' => true,
            'attendance' => $attendance
        ]);
    } catch (Exception $e) {
        file_put_contents('error.log', 'Today Action Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        echo json_encode([
            'success' => false,
            'message' => 'Server error occurred'
        ]);
    }
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
    $status = isset($_GET['status']) ? $_GET['status'] : 'present';

    // Get course name
    $stmt = $conn->prepare("
        SELECT course_name
        FROM courses
        WHERE course_code = ?
    ");
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    $stmt->close();

    if (!$course) {
        echo json_encode(['success' => false, 'message' => 'Course not found']);
        exit;
    }

    $details = [];
    if ($status === 'present') {
        // Fetch present students
        $stmt = $conn->prepare("
            SELECT u.full_name AS student_name, u.index_no, a.status
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            WHERE a.course_code = ? AND a.status = 'present' AND DATE(a.attendance_date) = CURDATE()
            ORDER BY u.full_name
        ");
        $stmt->bind_param("s", $course_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $details = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } elseif ($status === 'absent') {
        // Fetch absent students (enrolled but no attendance record for today)
        $stmt = $conn->prepare("
            SELECT u.full_name AS student_name, u.index_no, 'absent' AS status
            FROM course_enrollments ce
            JOIN users u ON ce.user_id = u.id
            WHERE ce.course_code = ?
            AND ce.user_id NOT IN (
                SELECT user_id 
                FROM attendance 
                WHERE course_code = ? 
                AND DATE(attendance_date) = CURDATE()
            )
            ORDER BY u.full_name
        ");
        $stmt->bind_param("ss", $course_code, $course_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $details = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid status parameter']);
        exit;
    }

    // Log the results for debugging
    file_put_contents('debug.log', print_r(['details_action' => $details, 'course_code' => $course_code, 'status' => $status, 'date' => date('Y-m-d')], true), FILE_APPEND);

    echo json_encode([
        'success' => true,
        'course_name' => $course['course_name'],
        'details' => $details
    ]);
} elseif ($action === 'generate_code' && $_SESSION['role'] === 'lecturer') {
    $data = json_decode(file_get_contents('php://input'), true);
    $course_code = isset($data['course_code']) ? $data['course_code'] : '';

    if (!$course_code) {
        echo json_encode(['success' => false, 'message' => 'Course code is required']);
        exit;
    }

    // Verify course assignment and get venue, latitude, longitude
    $stmt = $conn->prepare("
        SELECT t.venue, t.latitude, t.longitude
        FROM course_assignments ca
        JOIN timetable t ON ca.course_code = t.course_code
        WHERE ca.course_code = ? 
        AND ca.lecturer_id = ?
        AND t.day_of_week = DAYNAME(CURDATE())
        LIMIT 1
    ");
    $stmt->bind_param("si", $course_code, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid course, not assigned to you, or no schedule today']);
        $stmt->close();
        exit;
    }
    
    $row = $result->fetch_assoc();
    $venue = $row['venue'];
    $latitude = $row['latitude'];
    $longitude = $row['longitude'];
    $stmt->close();

    if ($latitude === null || $longitude === null) {
        echo json_encode(['success' => false, 'message' => 'No geolocation data available for this course schedule']);
        exit;
    }

    // Generate 6-character alphanumeric code
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }

    // Store code in attendance_codes table with 5-minute expiry
    $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    $stmt = $conn->prepare("
        INSERT INTO attendance_codes (course_code, code, venue, latitude, longitude, expires_at, lecturer_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssddsi", $course_code, $code, $venue, $latitude, $longitude, $expires_at, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'code' => $code
    ]);
} elseif ($action === 'submit_attendance' && $_SESSION['role'] === 'student') {
    $data = json_decode(file_get_contents('php://input'), true);
    $code = isset($data['code']) ? strtoupper(trim($data['code'])) : '';
    $student_lat = isset($data['latitude']) ? $data['latitude'] : null;
    $student_lon = isset($data['longitude']) ? $data['longitude'] : null;

    if (!$code || strlen($code) !== 6) {
        echo json_encode(['success' => false, 'message' => 'Invalid attendance code']);
        exit;
    }
    if ($student_lat === null || $student_lon === null) {
        echo json_encode(['success' => false, 'message' => 'Geolocation is required']);
        exit;
    }

    // Verify code and check geolocation
    $stmt = $conn->prepare("
        SELECT course_code, latitude, longitude
        FROM attendance_codes
        WHERE code = ? 
        AND expires_at > NOW()
        AND lecturer_id IS NOT NULL
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
    $course_code = $code_data['course_code'];
    $code_latitude = $code_data['latitude'];
    $code_longitude = $code_data['longitude'];
    $stmt->close();

    // Geolocation check (within ~100 meters)
    $distance = calculateDistance($student_lat, $student_lon, $code_latitude, $code_longitude);
    if ($distance > 0.1) { // 0.1 km = 100 meters
        echo json_encode(['success' => false, 'message' => 'You are not at the correct location']);
        exit;
    }

    // Verify student enrollment
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM course_enrollments
        WHERE user_id = ? AND course_code = ?
    ");
    $stmt->bind_param("is", $user_id, $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()['count'] === 0) {
        echo json_encode(['success' => false, 'message' => 'Not enrolled in this course']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Record attendance
    $stmt = $conn->prepare("
        INSERT INTO attendance (user_id, course_code, attendance_date, status, latitude, longitude)
        VALUES (?, ?, CURDATE(), 'present', ?, ?)
        ON DUPLICATE KEY UPDATE status = 'present', latitude = ?, longitude = ?
    ");
    $stmt->bind_param("isdddd", $user_id, $course_code, $student_lat, $student_lon, $student_lat, $student_lon);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Attendance recorded']);
} elseif ($action === 'download_report' && $_SESSION['role'] === 'lecturer') {
    $course_code = isset($_GET['course_code']) ? $_GET['course_code'] : '';
    $period = isset($_GET['period']) ? $_GET['period'] : '';
    $week = isset($_GET['week']) ? $_GET['week'] : '';

    if (!$course_code || !$period) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }

    // Log request parameters for debugging
    file_put_contents('debug.log', print_r(['download_report_params' => [
        'course_code' => $course_code,
        'period' => $period,
        'week' => $week,
        'lecturer_id' => $user_id,
        'date' => date('Y-m-d')
    ]], true), FILE_APPEND);

    // Get all relevant dates for the period
    $date_condition = '';
    $params = [$user_id];
    $types = "i";
    if ($period === 'today') {
        $date_condition = "AND t.day_of_week = DAYNAME(CURDATE())";
    } elseif ($period === 'this_week') {
        $date_condition = "AND t.day_of_week IN (
            SELECT DAYNAME(DATE_SUB(CURDATE(), INTERVAL n DAY))
            FROM (SELECT a.N + b.N * 10 + 1 AS n
                  FROM (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) a,
                       (SELECT 0 AS N) b) numbers
            WHERE n <= 6
        )";
    } elseif ($period === 'specific_week') {
        if (!$week) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Week parameter required']);
            exit;
        }
        // Convert week to date range
        $year = substr($week, 0, 4);
        $week_num = substr($week, 6);
        $start_date = date('Y-m-d', strtotime("$year-W$week_num-1")); // Monday
        $end_date = date('Y-m-d', strtotime("$year-W$week_num-7")); // Sunday
        $date_condition = "AND DATE(a.attendance_date) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= "ss";
    } elseif ($period !== 'all_time') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid period']);
        exit;
    }

    // Base query for timetable days and enrolled students
    $query = "
        SELECT DISTINCT c.course_name, COALESCE(a.attendance_date, CURDATE()) AS attendance_date, 
               u.full_name AS student_name, u.index_no,
               CASE WHEN a.id IS NOT NULL THEN a.status ELSE 'absent' END AS status
        FROM timetable t
        JOIN course_assignments ca ON t.course_code = ca.course_code
        JOIN courses c ON t.course_code = c.course_code
        JOIN course_enrollments ce ON t.course_code = ce.course_code
        JOIN users u ON ce.user_id = u.id
        LEFT JOIN attendance a ON a.user_id = ce.user_id 
            AND a.course_code = t.course_code 
            AND DATE(a.attendance_date) = CURDATE()
        WHERE ca.lecturer_id = ?
        $date_condition
    ";

    if ($course_code !== 'all') {
        $query .= " AND t.course_code = ?";
        $params[] = $course_code;
        $types .= "s";
    }

    $query .= " ORDER BY attendance_date DESC, c.course_name, u.full_name";

    // Log the query for debugging
    file_put_contents('debug.log', print_r(['download_report_query' => $query, 'params' => $params], true), FILE_APPEND);

    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance = [];
        while ($row = $result->fetch_assoc()) {
            $attendance[] = $row;
        }
        $stmt->close();

        // Log query results for debugging
        file_put_contents('debug.log', print_r(['download_report_results' => $attendance], true), FILE_APPEND);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance_report.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel compatibility
        fputcsv($output, ['Course', 'Date', 'Student Name', 'Index Number', 'Status']);

        if (empty($attendance)) {
            fputcsv($output, ['No attendance records found for the selected period']);
        } else {
            foreach ($attendance as $record) {
                fputcsv($output, [
                    $record['course_name'],
                    $record['attendance_date'],
                    $record['student_name'],
                    $record['index_no'],
                    $record['status']
                ]);
            }
        }

        fclose($output);
        exit;
    } catch (Exception $e) {
        file_put_contents('error.log', 'Download Report Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        exit;
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>