<?php
include '../db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$level = $_GET['level'] ?? '';

if (!$level) {
    echo json_encode(['success' => false, 'message' => 'Level is required']);
    exit;
}

$stmt = $conn->prepare("
    SELECT t.*, c.course_name, u.full_name as lecturer_name 
    FROM timetable t
    LEFT JOIN courses c ON t.course_code = c.course_code
    LEFT JOIN course_assignments ca ON t.course_code = ca.course_code
    LEFT JOIN users u ON ca.lecturer_id = u.id
    WHERE t.level = ?
    ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), t.start_time
");
$stmt->bind_param("s", $level);
$stmt->execute();
$result = $stmt->get_result();

$entries = [];
while ($row = $result->fetch_assoc()) {
    $entries[] = $row;
}

echo json_encode(['success' => true, 'entries' => $entries]);
$stmt->close();
?>