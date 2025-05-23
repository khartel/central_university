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

// Add course
$successMsg = $errorMsg = '';
if (isset($_POST['add_course'])) {
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $level = trim($_POST['level']);
    $credit_hours = trim($_POST['credit_hours']);

    // Basic validation
    if (empty($course_code)) {
        $errorMsg = "Course code cannot be empty";
    } elseif (empty($course_name)) {
        $errorMsg = "Course name cannot be empty";
    } else {
        $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, level, credit_hours) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $course_code, $course_name, $level, $credit_hours);
        if ($stmt->execute()) {
            $successMsg = "Course <b>$course_code - $course_name</b> added successfully.";
        } else {
            $errorMsg = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Stats
$totalCourses = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
$totalLevel100 = $conn->query("SELECT COUNT(*) as count FROM courses WHERE level='100'")->fetch_assoc()['count'];
$totalLevel200 = $conn->query("SELECT COUNT(*) as count FROM courses WHERE level='200'")->fetch_assoc()['count'];
$totalLevel300 = $conn->query("SELECT COUNT(*) as count FROM courses WHERE level='300'")->fetch_assoc()['count'];
$totalLevel400 = $conn->query("SELECT COUNT(*) as count FROM courses WHERE level='400'")->fetch_assoc()['count'];

// Search & Filter
$search = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
$filterLevel = isset($_GET['level']) ? $_GET['level'] : '';
$query = "SELECT course_code, course_name, level, credit_hours FROM courses";
$conditions = [];

if (!empty($filterLevel)) {
    $conditions[] = "level = '" . $conn->real_escape_string($filterLevel) . "'";
}
if (!empty($search)) {
    $searchEscaped = $conn->real_escape_string($search);
    $conditions[] = "(LOWER(course_code) LIKE '%$searchEscaped%' OR LOWER(course_name) LIKE '%$searchEscaped%')";
}
if ($conditions) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}
$query .= " ORDER BY course_code ASC";
$courses = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Course Management</title>
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
        
        .stat-icon.level100 {
            color: #4cc9f0;
        }
        
        .stat-icon.level200 {
            color: #4895ef;
        }
        
        .stat-icon.level300 {
            color: #4361ee;
        }
        
        .stat-icon.level400 {
            color: #3f37c9;
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
                <i class="fas fa-layer-group stat-icon level100"></i>
                <p class="stat-label">Level 100</p>
                <p class="stat-value"><?php echo $totalLevel100; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-layer-group stat-icon level200"></i>
                <p class="stat-label">Level 200</p>
                <p class="stat-value"><?php echo $totalLevel200; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-layer-group stat-icon level300"></i>
                <p class="stat-label">Level 300</p>
                <p class="stat-value"><?php echo $totalLevel300; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-layer-group stat-icon level400"></i>
                <p class="stat-label">Level 400</p>
                <p class="stat-value"><?php echo $totalLevel400; ?></p>
            </div>
        </div>

        <!-- Add Course Form -->
        <div class="card">
            <h2 class="card-title">Add New Course</h2>
            <form method="POST">
                <input type="hidden" name="add_course" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Course Code</label>
                        <input type="text" name="course_code" class="form-control" required 
                               placeholder="e.g., CSC101 or ABCD1234">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course Name</label>
                        <input type="text" name="course_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Level</label>
                        <select name="level" class="form-control" required>
                            <option value="100">Level 100</option>
                            <option value="200">Level 200</option>
                            <option value="300">Level 300</option>
                            <option value="400">Level 400</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Credit Hours</label>
                        <input type="number" name="credit_hours" class="form-control" required min="1" max="10">
                    </div>
                </div>
                <div class="form-group" style="text-align: right; margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">Add Course</button>
                </div>
            </form>
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
                <a href="admin_addcourses.php" class="btn" style="margin-left: 10px;">Reset</a>
            </div>
        </form>

        <!-- Courses Table -->
        <div class="table-container">
            <div class="table-header">Course List</div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Level</th>
                            <th>Credit Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($courses)): ?>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td>Level <?php echo htmlspecialchars($course['level']); ?></td>
                                    <td><?php echo htmlspecialchars($course['credit_hours']); ?></td>
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

    <script>
        // Validate credit hours input
        document.querySelector('input[name="credit_hours"]').addEventListener('change', function() {
            const value = parseInt(this.value);
            if (value < 1) this.value = 1;
            if (value > 10) this.value = 10;
        });
    </script>
</body>
</html>