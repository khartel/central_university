<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';
$courses = $input['courses'] ?? [];

if (empty($password) || empty($courses)) {
    echo json_encode(['success' => false, 'message' => 'Password and courses are required']);
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    exit();
}

$stmt = $conn->prepare("INSERT INTO course_enrollments (user_id, course_code) VALUES (?, ?)");
foreach ($courses as $course_code) {
    $stmt->bind_param("is", $user_id, $course_code);
    $stmt->execute();
}

echo json_encode(['success' => true, 'message' => 'Courses enrolled successfully']);
?>