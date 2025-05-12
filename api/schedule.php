<?php
session_start();
include '../db.php';

header('Content-Type: application/json');

// Check if user is logged in and has a valid role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['lecturer', 'student'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$courses = [];
$timetable = [];

if ($role === 'lecturer') {
    // Fetch assigned courses for lecturer
    $stmt = $conn->prepare("
        SELECT c.course_code, c.course_name, c.level
        FROM course_assignments ca
        JOIN courses c ON ca.course_code = c.course_code
        WHERE ca.lecturer_id = ?
        ORDER BY c.course_code
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $courses_result = $stmt->get_result();
    $courses = $courses_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch timetable for assigned courses
    $stmt = $conn->prepare("
        SELECT t.*, c.course_name, u.full_name AS lecturer_name
        FROM timetable t
        JOIN course_assignments ca ON t.course_code = ca.course_code
        JOIN courses c ON t.course_code = c.course_code
        LEFT JOIN users u ON ca.lecturer_id = u.id
        WHERE ca.lecturer_id = ?
        ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), t.start_time
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $timetable_result = $stmt->get_result();
    $timetable = $timetable_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} elseif ($role === 'student') {
    // Fetch enrolled courses for student
    $stmt = $conn->prepare("
        SELECT c.course_code, c.course_name, c.level
        FROM course_enrollments ce
        JOIN courses c ON ce.course_code = c.course_code
        WHERE ce.user_id = ?
        ORDER BY c.course_code
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $courses_result = $stmt->get_result();
    $courses = $courses_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch timetable for enrolled courses
    $stmt = $conn->prepare("
        SELECT t.*, c.course_name, u.full_name AS lecturer_name
        FROM timetable t
        JOIN course_enrollments ce ON t.course_code = ce.course_code
        JOIN courses c ON t.course_code = c.course_code
        LEFT JOIN course_assignments ca ON t.course_code = ca.course_code
        LEFT JOIN users u ON ca.lecturer_id = u.id
        WHERE ce.user_id = ?
        ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), t.start_time
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $timetable_result = $stmt->get_result();
    $timetable = $timetable_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'courses' => $courses,
    'timetable' => $timetable
]);
?>