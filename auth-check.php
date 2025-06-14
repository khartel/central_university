<?php
session_start();
header('Content-Type: application/json');

// Define allowed roles for dashboard pages
$allowedRoles = [
    'lecturer-dashboard.html' => ['lecturer'],
    'student-dashboard.html' => ['student']
];

// Get the requested page from X-Requested-Page header
$requestedPage = $_SERVER['HTTP_X_REQUESTED_PAGE'] ?? basename(parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?? '');

// Default response
$response = [
    'authenticated' => false,
    'role' => null,
    'allowed' => false
];

// Check if user is authenticated
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    $response['authenticated'] = true;
    $response['role'] = $_SESSION['role'];
    
    // Verify user exists in database
    require_once 'db.php';
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = ?");
    $stmt->bind_param("is", $_SESSION['user_id'], $_SESSION['role']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Check if user's role is allowed for the requested page
        if (isset($allowedRoles[$requestedPage])) {
            $response['allowed'] = in_array($_SESSION['role'], $allowedRoles[$requestedPage]);
        } else {
            // Allow access for non-restricted pages
            $response['allowed'] = true;
        }
    } else {
        // User doesn't exist or role changed
        session_destroy();
        $response = [
            'authenticated' => false,
            'role' => null,
            'allowed' => false
        ];
    }
    $stmt->close();
    $conn->close();
}

echo json_encode($response);
?>