<?php
include '../db.php';
session_start();

// logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Authentication - Show login page if not authenticated
if (!isset($_SESSION['admin_authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
        if ($_POST['admin_password'] === 'password') {
            $_SESSION['admin_authenticated'] = true;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $auth_error = "Invalid password.";
        }
    }
    
    // Show login page
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

// Check if course_assignments table exists, create if not
$checkTable = $conn->query("SHOW TABLES LIKE 'course_assignments'");
if ($checkTable->num_rows == 0) {
    $conn->query("CREATE TABLE course_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(10) NOT NULL,
        lecturer_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_code) REFERENCES courses(course_code) ON DELETE CASCADE,
        FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_assignment (course_code, lecturer_id)
    )");
}

// Handle course assignment
$successMsg = $errorMsg = '';
if (isset($_POST['assign_course'])) {
    $course_code = trim($_POST['course_code']);
    $lecturer_id = trim($_POST['lecturer_id']);
    
    if (empty($course_code) || empty($lecturer_id)) {
        $errorMsg = "Please select both course and lecturer";
    } else {
        // Check if assignment already exists
        $check = $conn->prepare("SELECT id FROM course_assignments WHERE course_code = ? AND lecturer_id = ?");
        $check->bind_param("si", $course_code, $lecturer_id);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $errorMsg = "This course is already assigned to the selected lecturer";
        } else {
            $stmt = $conn->prepare("INSERT INTO course_assignments (course_code, lecturer_id) VALUES (?, ?)");
            $stmt->bind_param("si", $course_code, $lecturer_id);
            if ($stmt->execute()) {
                $successMsg = "Course assigned successfully";
            } else {
                $errorMsg = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Handle course unassignment
if (isset($_GET['unassign'])) {
    $assignment_id = (int)$_GET['unassign'];
    $stmt = $conn->prepare("DELETE FROM course_assignments WHERE id = ?");
    $stmt->bind_param("i", $assignment_id);
    if ($stmt->execute()) {
        $successMsg = "Assignment removed successfully";
    } else {
        $errorMsg = "Error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Get all lecturers
$lecturers = $conn->query("SELECT id, full_name, email FROM users WHERE role = 'lecturer' ORDER BY full_name ASC")->fetch_all(MYSQLI_ASSOC);

// Get all courses
$courses = $conn->query("SELECT course_code, course_name, level FROM courses ORDER BY course_code ASC")->fetch_all(MYSQLI_ASSOC);

// Get current assignments with lecturer and course details
$assignments = $conn->query("
    SELECT ca.id, ca.course_code, c.course_name, ca.lecturer_id, u.full_name as lecturer_name, u.email as lecturer_email
    FROM course_assignments ca
    JOIN courses c ON ca.course_code = c.course_code
    JOIN users u ON ca.lecturer_id = u.id
    ORDER BY c.course_code ASC
")->fetch_all(MYSQLI_ASSOC);

// Search and filter
$search = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
$filterLevel = isset($_GET['level']) ? $_GET['level'] : '';

$query = "
    SELECT c.course_code, c.course_name, c.level, 
           GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ') as assigned_lecturers
    FROM courses c
    LEFT JOIN course_assignments ca ON c.course_code = ca.course_code
    LEFT JOIN users u ON ca.lecturer_id = u.id
";

$conditions = [];
if (!empty($filterLevel)) {
    $conditions[] = "c.level = '" . $conn->real_escape_string($filterLevel) . "'";
}
if (!empty($search)) {
    $searchEscaped = $conn->real_escape_string($search);
    $conditions[] = "(LOWER(c.course_code) LIKE '%$searchEscaped%' OR LOWER(c.course_name) LIKE '%$searchEscaped%')";
}

if ($conditions) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " GROUP BY c.course_code ORDER BY c.course_code ASC";
$courseAssignments = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Stats
$totalCourses = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
$totalAssigned = $conn->query("SELECT COUNT(DISTINCT course_code) as count FROM course_assignments")->fetch_assoc()['count'];
$totalLecturers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='lecturer'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Course Assignment</title>
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
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
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
        
        .stat-icon.courses {
            color: var(--primary);
        }
        
        .stat-icon.assigned {
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
        
        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background-color: #c1121f;
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
        
        .badge {
            display: inline-block;
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 50rem;
        }
        
        .badge-primary {
            color: #fff;
            background-color: var(--primary);
        }
        
        .badge-success {
            color: #fff;
            background-color: var(--success);
        }
        
        .badge-warning {
            color: #000;
            background-color: var(--warning);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-container, .container {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
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
                <i class="fas fa-book stat-icon courses"></i>
                <p class="stat-label">Total Courses</p>
                <p class="stat-value"><?php echo $totalCourses; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle stat-icon assigned"></i>
                <p class="stat-label">Assigned Courses</p>
                <p class="stat-value"><?php echo $totalAssigned; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-chalkboard-teacher stat-icon lecturers"></i>
                <p class="stat-label">Lecturers</p>
                <p class="stat-value"><?php echo $totalLecturers; ?></p>
            </div>
        </div>

        <!-- Assign Course Form -->
        <div class="card">
            <h2 class="card-title">Assign Course to Lecturer</h2>
            <form method="POST">
                <input type="hidden" name="assign_course" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Select Course</label>
                        <select name="course_code" class="form-control" required>
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course['course_code']); ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Select Lecturer</label>
                        <select name="lecturer_id" class="form-control" required>
                            <option value="">-- Select Lecturer --</option>
                            <?php foreach ($lecturers as $lecturer): ?>
                                <option value="<?php echo htmlspecialchars($lecturer['id']); ?>">
                                    <?php echo htmlspecialchars($lecturer['full_name'] . ' (' . $lecturer['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="text-align: right; margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">Assign Course</button>
                </div>
            </form>
        </div>

        <!-- Current Assignments -->
        <div class="card">
            <h2 class="card-title">Current Assignments</h2>
            <?php if (!empty($assignments)): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Lecturer</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($assignment['course_code']); ?></strong><br>
                                        <?php echo htmlspecialchars($assignment['course_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($assignment['lecturer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['lecturer_email']); ?></td>
                                    <td>
                                        <a href="?unassign=<?php echo $assignment['id']; ?>" class="btn btn-danger" 
                                           onclick="return confirm('Are you sure you want to unassign this course?')">
                                            <i class="fas fa-times"></i> Unassign
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 1.5rem; color: var(--gray);">
                    No course assignments found.
                </p>
            <?php endif; ?>
        </div>

        <!-- Search + Filter -->
        <form method="GET" class="filter-section">
            <div class="filter-group">
                <label class="form-label">Search by code/name</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       class="form-control" placeholder="Search by course code or name...">
            </div>
            <div class="filter-group">
                <label class="form-label">Filter by level</label>
                <select name="level" class="form-control">
                    <option value="">All Levels</option>
                    <option value="100" <?php if ($filterLevel == '100') echo 'selected'; ?>>Level 100</option>
                    <option value="200" <?php if ($filterLevel == '200') echo 'selected'; ?>>Level 200</option>
                    <option value="300" <?php if ($filterLevel == '300') echo 'selected'; ?>>Level 300</option>
                    <option value="400" <?php if ($filterLevel == '400') echo 'selected'; ?>>Level 400</option>
                </select>
            </div>
            <div class="filter-group">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="admin_assigncourse.php" class="btn" style="margin-left: 10px;">Reset</a>
            </div>
        </form>

        <!-- Courses Table -->
        <div class="table-container">
            <div class="table-header">All Courses</div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Level</th>
                            <th>Assigned Lecturers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($courseAssignments)): ?>
                            <?php foreach ($courseAssignments as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td>
                                        <span class="badge badge-primary">Level <?php echo htmlspecialchars($course['level']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($course['assigned_lecturers'])): ?>
                                            <span class="badge badge-success"><?php echo htmlspecialchars($course['assigned_lecturers']); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 2rem; color: var(--gray);">
                                    No courses found. <?php if (!empty($search) || !empty($filterLevel)) echo 'Try different search criteria.'; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>