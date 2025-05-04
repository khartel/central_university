<?php
session_start();
include '../db.php';

header('Content-Type: application/json');

// Check if user is logged in and is a lecturer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch assigned courses
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
    SELECT t.*, c.course_name
    FROM timetable t
    JOIN course_assignments ca ON t.course_code = ca.course_code
    JOIN courses c ON t.course_code = c.course_code
    WHERE ca.lecturer_id = ?
    ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), t.start_time
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$timetable_result = $stmt->get_result();
$timetable = $timetable_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'success' => true,
    'courses' => $courses,
    'timetable' => $timetable
]);
?>