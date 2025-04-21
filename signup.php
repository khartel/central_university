<?php
include 'db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    if ($action === 'checkEmail') {
        $email = $data['email'] ?? '';
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }

        if (!str_ends_with($email, '@central.edu.gh')) {
            throw new Exception('Email must end with @central.edu.gh');
        }

        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Email not found. Please contact admin to register.');
        }

        $user = $result->fetch_assoc();

        echo json_encode([
            'success' => true,
            'hasPassword' => !empty($user['password']),
            'role' => $user['role']
        ]);

    } elseif ($action === 'createPassword') {
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // Password requirements
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long.');
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            throw new Exception('Password must contain at least one uppercase letter.');
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            throw new Exception('Password must contain at least one lowercase letter.');
        }
        
        if (!preg_match('/[0-9]/', $password) && !preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new Exception('Password must contain at least one number or symbol.');
        }

        // Check if user exists and doesn't have a password
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND password IS NULL");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Password already set or email not found.');
        }

        // Hash and update password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $updateStmt->bind_param("ss", $hashedPassword, $email);
        
        if ($updateStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Password created successfully.'
            ]);
        } else {
            throw new Exception('Failed to create password. Please try again.');
        }
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>