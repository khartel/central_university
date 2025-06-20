<?php
include '../db.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Logout functionality
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Authentication system
if (!isset($_SESSION['admin_authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
        $hashed_password = password_hash('password', PASSWORD_DEFAULT);
        if (password_verify($_POST['admin_password'], $hashed_password)) {
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_last_activity'] = time();
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
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
            <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
            <style>
                :root {
                    --primary: #4361ee;
                    --primary-dark: #3a56d4;
                    --secondary: #3f37c9;
                    --success: #4cc9f0;
                    --danger: #f72585;
                    --warning: #f8961e;
                    --info: #4895ef;
                    --light: #f8f9fa;
                    --dark: #212529;
                    --white: #ffffff;
                    --gray: #6c757d;
                    --gray-light: #e9ecef;
                }
                
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: 'Poppins', sans-serif;
                    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                    min-height: 100vh;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    padding: 20px;
                }
                
                .login-container {
                    background: var(--white);
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                    width: 100%;
                    max-width: 400px;
                    padding: 30px;
                    text-align: center;
                }
                
                .login-icon {
                    color: var(--primary);
                    font-size: 2.5rem;
                    margin-bottom: 15px;
                }
                
                .login-title {
                    font-size: 1.5rem;
                    font-weight: 600;
                    color: var(--dark);
                    margin-bottom: 20px;
                }
                
                .error-message {
                    background-color: #fee2e2;
                    color: #b91c1c;
                    padding: 10px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                    font-size: 0.9rem;
                }
                
                .form-input {
                    width: 100%;
                    padding: 12px 15px;
                    border: 1px solid var(--gray-light);
                    border-radius: 5px;
                    font-size: 1rem;
                    margin-bottom: 20px;
                    transition: border-color 0.3s;
                }
                
                .form-input:focus {
                    outline: none;
                    border-color: var(--primary);
                }
                
                .btn {
                    background-color: var(--primary);
                    color: var(--white);
                    border: none;
                    border-radius: 5px;
                    padding: 12px;
                    font-size: 1rem;
                    cursor: pointer;
                    width: 100%;
                    transition: background-color 0.3s;
                }
                
                .btn:hover {
                    background-color: var(--primary-dark);
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <div class="login-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h1 class="login-title">Admin Access</h1>
                <?php if (isset($auth_error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($auth_error); ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="password" name="admin_password" placeholder="Enter password" required class="form-input">
                    <button type="submit" class="btn">Login</button>
                </form>
            </div>
        </body>
        </html>
        <?php exit; 
    }
}

// Session timeout (30 minutes)
if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
$_SESSION['admin_last_activity'] = time();

// Add user functionality
$successMsg = $errorMsg = '';
if (isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    
    if ($role === 'student') {
        $index_no = trim($_POST['index_no']);
        $program = trim($_POST['program']);
        $level = trim($_POST['level']);
        
        // Validate student index number format
        if (!preg_match('/^[A-Za-z0-9]{8,20}$/', $index_no)) {
            $errorMsg = "Invalid index number format. Use 8-20 alphanumeric characters.";
        }
    } else {
        $index_no = NULL; // No index number for lecturers
    }

    if (empty($errorMsg)) {
        if (!str_ends_with($email, '@central.edu.gh')) {
            $errorMsg = "Email must end with @central.edu.gh";
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Check if email already exists
                $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check_email->bind_param("s", $email);
                $check_email->execute();
                $check_email->store_result();
                
                if ($check_email->num_rows > 0) {
                    $errorMsg = "Error: Email already exists.";
                    $check_email->close();
                    throw new Exception($errorMsg);
                }
                $check_email->close();
                
                // For students, check if index number exists
                if ($role === 'student') {
                    $check_index = $conn->prepare("SELECT id FROM users WHERE index_no = ?");
                    $check_index->bind_param("s", $index_no);
                    $check_index->execute();
                    $check_index->store_result();
                    
                    if ($check_index->num_rows > 0) {
                        $errorMsg = "Error: Index number already exists.";
                        $check_index->close();
                        throw new Exception($errorMsg);
                    }
                    $check_index->close();
                }
                
                // Insert into users table
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, role, index_no, password) VALUES (?, ?, ?, ?, NULL)");
                $stmt->bind_param("ssss", $full_name, $email, $role, $index_no);
                
                if (!$stmt->execute()) {
                    $errorMsg = "Error adding user: " . $stmt->error;
                    $stmt->close();
                    throw new Exception($errorMsg);
                }
                $stmt->close();
                
                // If student, insert into students table
                if ($role === 'student') {
                    $stmt = $conn->prepare("INSERT INTO students (index_no, full_name, email, program, level) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $index_no, $full_name, $email, $program, $level);
                    
                    if (!$stmt->execute()) {
                        $errorMsg = "Error adding student details: " . $stmt->error;
                        $stmt->close();
                        throw new Exception($errorMsg);
                    }
                    $stmt->close();
                }
                
                // Commit transaction
                $conn->commit();
                $successMsg = "User <b>" . htmlspecialchars($full_name) . "</b> added as <b>" . htmlspecialchars($role) . "</b>. They'll set their password later.";
                
            } catch (Exception $e) {
                $conn->rollback();
                if (empty($errorMsg)) $errorMsg = "An error occurred during registration.";
            }
        }
    }
}

// Get statistics
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$totalStudents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='student'")->fetch_assoc()['count'];
$totalLecturers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='lecturer'")->fetch_assoc()['count'];

// Search and filter functionality
$search = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
$filterRole = isset($_GET['role']) ? $_GET['role'] : '';
$query = "SELECT id, full_name, email, role, password, index_no FROM users";
$conditions = [];

if (!empty($filterRole)) {
    $conditions[] = "role = '" . $conn->real_escape_string($filterRole) . "'";
}
if (!empty($search)) {
    $searchEscaped = $conn->real_escape_string($search);
    $conditions[] = "(LOWER(full_name) LIKE '%$searchEscaped%' OR LOWER(email) LIKE '%$searchEscaped%' OR index_no LIKE '%$searchEscaped%')";
}
if ($conditions) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}
$query .= " ORDER BY created_at DESC";
$users = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - User Management</title>
    <link rel="shortcut icon" href="pics/cu-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --white: #ffffff;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --border-radius: 8px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
        }
        
        a {
            text-decoration: none;
            color: inherit;
        }
        
        /* Header */
        .header {
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-icon {
            width: 24px;
            height: 24px;
            object-fit: cover;
            border-radius: 50%;
            margin-right: 0.5rem;
            vertical-align: middle;
        }

        .logo-text {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .logout-btn {
            color: var(--danger);
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .logout-btn:hover {
            color: #c1121f;
        }
        
        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-icon.users {
            color: var(--primary);
        }
        
        .stat-icon.students {
            color: var(--success);
        }
        
        .stat-icon.lecturers {
            color: var(--secondary);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        /* Forms */
        .card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--gray);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        /* Student Fields Section */
        #studentFields {
            display: none;
            grid-column: 1 / -1;
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-top: -1rem;
            margin-bottom: 1rem;
        }
        
        #studentFields .form-grid {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }
        
        /* Filter Section */
        .filter-section {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
            margin-bottom: 2rem;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        /* Table */
        .table-container {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-light);
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem 1.5rem;
            text-align: left;
        }
        
        thead {
            background-color: var(--gray-light);
        }
        
        th {
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
        }
        
        tbody tr {
            border-bottom: 1px solid var(--gray-light);
            transition: var(--transition);
        }
        
        tbody tr:last-child {
            border-bottom: none;
        }
        
        tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .status-active {
            color: #16a34a;
            font-weight: 500;
        }
        
        .status-inactive {
            color: #dc2626;
            font-weight: 500;
        }
        
        .capitalize {
            text-transform: capitalize;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-container, .container {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            th, td {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <div class="logo">
                <img src="pics/cu-logo.png" class="logo-icon" alt="Central University Logo">
                <span class="logo-text">Central University</span>
            </div>
            <a href="admin.php?logout=1" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container">
        <!-- Messages -->
        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?php echo $successMsg; ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-error"><?php echo $errorMsg; ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users stat-icon users"></i>
                <p class="stat-label">Total Users</p>
                <p class="stat-value"><?php echo $totalUsers; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-graduate stat-icon students"></i>
                <p class="stat-label">Students</p>
                <p class="stat-value"><?php echo $totalStudents; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-chalkboard-teacher stat-icon lecturers"></i>
                <p class="stat-label">Lecturers</p>
                <p class="stat-value"><?php echo $totalLecturers; ?></p>
            </div>
        </div>

        <!-- Add User Form -->
        <div class="card">
            <h2 class="card-title">Add New User</h2>
            <form method="POST">
                <input type="hidden" name="add_user" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email (@central.edu.gh)</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" id="roleSelect" class="form-control" required>
                            <option value="student">Student</option>
                            <option value="lecturer">Lecturer</option>
                        </select>
                    </div>
                </div>
                
                <!-- Student-specific fields (hidden by default) -->
                <div id="studentFields">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Index Number</label>
                            <input type="text" name="index_no" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Program</label>
                            <input type="text" name="program" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Level</label>
                            <select name="level" class="form-control">
                                <option value="100">Level 100</option>
                                <option value="200">Level 200</option>
                                <option value="300">Level 300</option>
                                <option value="400">Level 400</option>
                                <option value="500">Level 500</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" style="text-align: right; margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>

        <!-- Search + Filter -->
        <form method="GET" class="filter-section">
            <div class="filter-group">
                <label class="form-label">Search by name/email/index</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       class="form-control" placeholder="Search by name, email or index number...">
            </div>
            <div class="filter-group">
                <label class="form-label">Filter by role</label>
                <select name="role" class="form-control">
                    <option value="">All</option>
                    <option value="student" <?php if ($filterRole == 'student') echo 'selected'; ?>>Students</option>
                    <option value="lecturer" <?php if ($filterRole == 'lecturer') echo 'selected'; ?>>Lecturers</option>
                </select>
            </div>
            <div class="filter-group">
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>

        <!-- Users Table -->
        <div class="table-container">
            <div class="table-header">User List</div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Index No</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><?php echo $u['index_no'] ? htmlspecialchars($u['index_no']) : 'N/A'; ?></td>
                                <td class="capitalize"><?php echo $u['role']; ?></td>
                                <td class="<?php echo $u['password'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $u['password'] ? 'Active' : 'Inactive'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--gray);">
                                    No users found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Show/hide student fields based on role selection
        document.getElementById('roleSelect').addEventListener('change', function() {
            const studentFields = document.getElementById('studentFields');
            const studentInputs = studentFields.querySelectorAll('input, select');
            
            if (this.value === 'student') {
                studentFields.style.display = 'block';
                studentInputs.forEach(input => {
                    if (input.name === 'index_no' || input.name === 'program') {
                        input.setAttribute('required', 'required');
                    }
                });
            } else {
                studentFields.style.display = 'none';
                studentInputs.forEach(input => {
                    input.removeAttribute('required');
                });
            }
        });

        // Initialize the display based on current selection
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('roleSelect');
            const studentFields = document.getElementById('studentFields');
            
            if (roleSelect.value === 'student') {
                studentFields.style.display = 'block';
                studentFields.querySelectorAll('input, select').forEach(input => {
                    if (input.name === 'index_no' || input.name === 'program') {
                        input.setAttribute('required', 'required');
                    }
                });
            }
        });
    </script>
</body>
</html>