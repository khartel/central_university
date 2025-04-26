<?php
session_start();
include 'db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.html');
    exit();
}

// Fetch user data from database
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header('Location: index.html');
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
        'email' => $user['email']
    ]
]);
?>