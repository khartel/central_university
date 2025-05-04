<?php
include 'db.php';
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
        if ($_POST['admin_password'] === 'password') {
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_last_activity'] = time();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
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
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
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
            min-height: calc(100vh - 120px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .dashboard-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: var(--dark);
            text-align: center;
        }
        
        .dashboard-subtitle {
            font-size: 1.1rem;
            color: var(--gray);
            margin-bottom: 3rem;
            text-align: center;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Action Cards */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .action-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 2.5rem 2rem;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            text-align: center;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }
        
        .action-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .action-description {
            font-size: 0.95rem;
            color: var(--gray);
            margin-bottom: 1.5rem;
        }
        
        .action-btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            background-color: var(--primary);
            color: var(--white);
            transition: var(--transition);
        }
        
        .action-btn:hover {
            background-color: var(--primary-dark);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-container, .container {
                padding: 1rem;
            }
            
            .dashboard-title {
                font-size: 1.5rem;
            }
            
            .dashboard-subtitle {
                font-size: 1rem;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
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
            <a href="?logout=1" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container">
        <h1 class="dashboard-title">Admin Dashboard</h1>
        <p class="dashboard-subtitle">
            Select the administrative task you want to perform from the options below.
            Each option will redirect you to the appropriate management interface.
        </p>
        
        <div class="actions-grid">
            <!-- Add New User -->
            <div class="action-card" onclick="window.location.href='admin_adduser.php'">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h3 class="action-title">Add New User</h3>
                <p class="action-description">
                    Register new students or lecturers to the system. Set up their basic information 
                    and they'll be able to set their passwords later.
                </p>
                <div class="action-btn">Manage Users</div>
            </div>
            
            <!-- Add New Course -->
            <div class="action-card" onclick="window.location.href='admin_addcourses.php'">
                <div class="action-icon">
                    <i class="fas fa-book-medical"></i>
                </div>
                <h3 class="action-title">Add New Course</h3>
                <p class="action-description">
                    Create new courses in the system. Specify course codes, names, 
                    levels, and credit hours for each course.
                </p>
                <div class="action-btn">Manage Courses</div>
            </div>
            
            <!-- Assign Course to Lecturer -->
            <div class="action-card" onclick="window.location.href='admin_assigncourses.php'">
                <div class="action-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3 class="action-title">Assign Course to Lecturer</h3>
                <p class="action-description">
                    Assign courses to lecturers who will be teaching them. View and manage 
                    existing assignments as needed.
                </p>
                <div class="action-btn">Manage Assignments</div>
            </div>

            <!-- Create Timetable -->
            <div class="action-card" onclick="window.location.href='admin_timetable.php'">
                <div class="action-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3 class="action-title">Create Timetable</h3>
                <p class="action-description">
                    Create and manage course timetables for each level. Assign courses to specific days, 
                    times, and venues, and assign lecturers to each session.
                </p>
                <div class="action-btn">Manage Timetable</div>
            </div>
        </div>
    </main>

    <script>
        // Simple confirmation for logout
        document.querySelector('.logout-btn').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>