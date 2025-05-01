<?php
include 'db.php';

header('Content-Type: application/json');

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

try {
    // Validate email format and domain
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address.');
    }
    
    if (!str_ends_with($email, '@central.edu.gh')) {
        throw new Exception('Email must end with @central.edu.gh');
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Email not found. Please contact admin to register.');
    }

    $user = $result->fetch_assoc();
    
    // Check if password is set
    if (empty($user['password'])) {
        throw new Exception('No password set. Please <a href="signup.html">create one</a>.');
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        throw new Exception('Incorrect password.');
    }

    // Start session and store user data
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $user['role'];
    
    echo json_encode([
        'success' => true,
        'role' => $user['role'],
        'user_id' => $user['id']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>