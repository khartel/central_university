<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$password = $data['password'] ?? '';
$courses = $data['courses'] ?? [];

if (empty($password) || empty($courses)) {
    echo json_encode(['success' => false, 'message' => 'Password and course selection are required']);
    exit();
}

// Verify password
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    exit();
}

// Drop courses
$placeholders = implode(',', array_fill(0, count($courses), '?'));
$stmt = $conn->prepare("DELETE FROM course_enrollments WHERE user_id = ? AND course_code IN ($placeholders)");
$params = array_merge([$user_id], $courses);
$types = str_repeat('s', count($courses)) . 'i';
$stmt->bind_param($types, ...array_reverse($params));
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No courses were dropped']);
}
?>