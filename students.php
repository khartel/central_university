<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

if ($action === 'levels') {
    $stmt = $conn->prepare("SELECT DISTINCT level FROM students ORDER BY level");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $levels = [];
    while ($row = $result->fetch_assoc()) {
        $levels[] = $row['level'];
    }
    
    echo json_encode(['success' => true, 'levels' => $levels]);
} elseif ($action === 'courses') {
    $stmt = $conn->prepare("
        SELECT c.course_code, c.course_name 
        FROM courses c
        JOIN course_assignments ca ON c.course_code = ca.course_code
        WHERE ca.lecturer_id = ? 
        ORDER BY c.course_name
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    
    echo json_encode(['success' => true, 'courses' => $courses]);
} elseif ($action === 'students_by_level') {
    $level = $_GET['level'] ?? '';
    if (!$level) {
        echo json_encode(['success' => false, 'message' => 'Level is required']);
        exit();
    }
    
    $stmt = $conn->prepare("
        SELECT full_name, index_no, level, program 
        FROM students 
        WHERE level = ? 
        ORDER BY full_name
    ");
    $stmt->bind_param("s", $level);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
} elseif ($action === 'students_by_course') {
    $course_code = $_GET['course_code'] ?? '';
    if (!$course_code) {
        echo json_encode(['success' => false, 'message' => 'Course code is required']);
        exit();
    }
    
    $stmt = $conn->prepare("
        SELECT s.full_name, s.index_no, s.level, s.program 
        FROM students s
        JOIN users u ON s.index_no = u.index_no
        JOIN course_enrollments ce ON u.id = ce.user_id 
        WHERE ce.course_code = ? 
        ORDER BY s.full_name
    ");
    $stmt->bind_param("s", $course_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>