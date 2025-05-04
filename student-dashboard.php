<?php
session_start();
include 'db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Fetch user data from database
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT u.full_name, u.email, u.index_no, s.program, s.level 
    FROM users u
    LEFT JOIN students s ON u.index_no = s.index_no
    WHERE u.id = ?
");
if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

$user = $result->fetch_assoc();

// Generate initials for avatar
$initials = '';
if (!empty($user['full_name'])) {
    $names = explode(' ', $user['full_name']);
    foreach ($names as $name) {
        $initials .= strtoupper(substr($name, 0, 1));
    }
    $initials = substr($initials, 0, 2);
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'user' => [
        'full_name' => $user['full_name'],
        'initials' => $initials,
        'email' => $user['email'],
        'index_no' => $user['index_no'],
        'program' => $user['program'],
        'level' => $user['level']
    ]
]);

$stmt->close();
$conn->close();
?>