<?php
include 'db.php';
session_start();

// logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Authentication
if (!isset($_SESSION['admin_authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
        if ($_POST['admin_password'] === 'password') {
            $_SESSION['admin_authenticated'] = true;
        } else {
            $auth_error = "Invalid password.";
        }
    }

    if (!isset($_SESSION['admin_authenticated'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Admin Login</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
            <style>body { font-family: 'Poppins', sans-serif; }</style>
        </head>
        <body class="bg-gradient-to-br from-blue-100 to-purple-100 flex items-center justify-center h-screen">
            <form method="post" class="bg-white p-8 rounded shadow-md w-96">
                <div class="text-center mb-4">
                    <i class="fas fa-lock text-3xl text-blue-600"></i>
                    <h2 class="text-lg font-semibold mt-2">Admin Access</h2>
                </div>
                <?php if (isset($auth_error)): ?>
                    <div class="bg-red-100 text-red-700 p-2 rounded mb-3 text-sm"><?php echo htmlspecialchars($auth_error); ?></div>
                <?php endif; ?>
                <input type="password" name="admin_password" placeholder="Enter password" required
                       class="w-full px-3 py-2 border rounded mb-4">
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">Login</button>
            </form>
        </body>
        </html>
        <?php exit; }
}

// Add user (no redirect)
$successMsg = $errorMsg = '';
if (isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);

    if (!str_ends_with($email, '@central.edu.gh')) {
        $errorMsg = "Email must end with @central.edu.gh";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $full_name, $email, $role);
        if ($stmt->execute()) {
            $successMsg = "User <b>$full_name</b> added as <b>$role</b>. They'll set their password later.";
        } else {
            $errorMsg = "Error: " . $stmt->error;
        }
    }
}

// Stats
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$totalStudents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='student'")->fetch_assoc()['count'];
$totalLecturers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='lecturer'")->fetch_assoc()['count'];

// Search & Filter
$search = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
$filterRole = isset($_GET['role']) ? $_GET['role'] : '';
$query = "SELECT * FROM users";
$conditions = [];

if (!empty($filterRole)) {
    $conditions[] = "role = '" . $conn->real_escape_string($filterRole) . "'";
}
if (!empty($search)) {
    $searchEscaped = $conn->real_escape_string($search);
    $conditions[] = "(LOWER(full_name) LIKE '%$searchEscaped%' OR LOWER(email) LIKE '%$searchEscaped%')";
}
if ($conditions) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}
$query .= " ORDER BY created_at DESC";
$users = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-100">

<!-- Top Nav -->
<nav class="bg-white shadow p-4">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <div class="flex items-center space-x-2">
            <i class="fas fa-graduation-cap text-blue-600 text-xl"></i>
            <span class="text-lg font-semibold">Central University - Admin</span>
        </div>
        <a href="?logout=1" class="text-red-600 hover:text-red-800">
            <i class="fas fa-sign-out-alt mr-1"></i> Logout
        </a>
    </div>
</nav>

<!-- Main Section -->
<div class="max-w-7xl mx-auto px-4 py-6">

    <!-- Messages -->
    <?php if ($successMsg): ?>
        <div class="mb-4 bg-green-100 text-green-800 px-4 py-2 rounded shadow"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="mb-4 bg-red-100 text-red-700 px-4 py-2 rounded shadow"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white shadow rounded p-4">
            <i class="fas fa-users text-blue-500 text-xl"></i>
            <p class="text-sm text-gray-500">Total Users</p>
            <p class="text-xl font-bold"><?php echo $totalUsers; ?></p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <i class="fas fa-user-graduate text-green-500 text-xl"></i>
            <p class="text-sm text-gray-500">Students</p>
            <p class="text-xl font-bold"><?php echo $totalStudents; ?></p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <i class="fas fa-chalkboard-teacher text-purple-500 text-xl"></i>
            <p class="text-sm text-gray-500">Lecturers</p>
            <p class="text-xl font-bold"><?php echo $totalLecturers; ?></p>
        </div>
    </div>

    <!-- Add User Form -->
    <div class="bg-white shadow rounded p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Add New User</h2>
        <form method="POST" class="grid gap-4 grid-cols-1 sm:grid-cols-2">
            <input type="hidden" name="add_user" value="1">
            <div>
                <label class="block text-sm text-gray-600">Full Name</label>
                <input type="text" name="full_name" class="w-full border rounded px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm text-gray-600">Email (@central.edu.gh)</label>
                <input type="email" name="email" class="w-full border rounded px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm text-gray-600">Role</label>
                <select name="role" class="w-full border rounded px-3 py-2" required>
                    <option value="student">Student</option>
                    <option value="lecturer">Lecturer</option>
                </select>
            </div>
            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Add User</button>
            </div>
        </form>
    </div>

    <!-- Search + Filter -->
    <form method="GET" class="flex flex-wrap gap-4 mb-4 items-end">
        <div>
            <label class="block text-sm text-gray-700">Search by name/email</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   class="px-3 py-2 border rounded w-64" placeholder="Search...">
        </div>
        <div>
            <label class="block text-sm text-gray-700">Filter by role</label>
            <select name="role" class="px-3 py-2 border rounded w-48">
                <option value="">All</option>
                <option value="student" <?php if ($filterRole == 'student') echo 'selected'; ?>>Students</option>
                <option value="lecturer" <?php if ($filterRole == 'lecturer') echo 'selected'; ?>>Lecturers</option>
            </select>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Apply</button>
    </form>

    <!-- Users Table -->
    <div class="bg-white shadow rounded">
        <div class="p-4 border-b text-lg font-semibold">User List</div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="text-left p-3">Name</th>
                        <th class="text-left p-3">Email</th>
                        <th class="text-left p-3">Role</th>
                        <th class="text-left p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="p-3"><?php echo htmlspecialchars($u['full_name']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td class="p-3 capitalize"><?php echo $u['role']; ?></td>
                            <td class="p-3 text-green-600">Active</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                        <tr><td class="p-3 text-gray-500" colspan="4">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
