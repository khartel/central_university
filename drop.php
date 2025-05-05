<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) ) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$password = $data['password'] ?? '';
$courses = $data['courses'] ?? [];

if (empty($password)){
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit();
}

if (empty($courses)) {
    echo json_encode(['success' => false, 'message' => 'Course selection is required']);
    exit();
}

// Verify password
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid password']);
    exit();
}

// Prepare the DELETE statement
$placeholders = implode(',', array_fill(0, count($courses), '?'));
$query = "DELETE FROM course_enrollments WHERE user_id = ? AND course_code IN ($placeholders)";
$stmt = $conn->prepare($query);

// Build types and parameters correctly
$types = 'i' . str_repeat('s', count($courses)); // 'i' for user_id, 's' for each course code
$params = array_merge([$user_id], $courses);

// Bind parameters
$stmt->bind_param($types, ...$params);

// Execute
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Courses dropped successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'No courses were dropped or courses not found']);
}

$stmt->close();
$conn->close();
?>