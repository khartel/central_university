<?php
include '../db.php';
session_start();

// Logout functionality
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
    
    include 'admin_login_page.php';
    exit;
}

// Handle form submissions
$successMsg = $errorMsg = '';

// Add timetable entry
if (isset($_POST['add_timetable'])) {
    $course_code = trim($_POST['course_code']);
    $level = trim($_POST['level']);
    $day_of_week = trim($_POST['day_of_week']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    $venue = trim($_POST['venue']);

    // Validate time
    if (strtotime($end_time) <= strtotime($start_time)) {
        $errorMsg = "End time must be after start time";
    } else {
        // Check for time slot conflict
        $stmt = $conn->prepare("
            SELECT id FROM timetable 
            WHERE level = ? AND day_of_week = ? AND venue = ? 
            AND (
                (start_time <= ? AND end_time > ?) OR 
                (start_time < ? AND end_time >= ?) OR 
                (start_time >= ? AND end_time <= ?)
            )
        ");
        $stmt->bind_param("sssssssss", $level, $day_of_week, $venue, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errorMsg = "Time slot conflict: The venue is already booked for this level and time.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO timetable (course_code, level, day_of_week, start_time, end_time, venue) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssss", $course_code, $level, $day_of_week, $start_time, $end_time, $venue);
            
            if ($stmt->execute()) {
                $successMsg = "Timetable entry added successfully!";
            } else {
                $errorMsg = "Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Delete timetable entry
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM timetable WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $successMsg = "Timetable entry deleted successfully!";
    } else {
        $errorMsg = "Error deleting entry: " . $stmt->error;
    }
    $stmt->close();
    
    header("Location: admin_timetable.php");
    exit;
}

// Get distinct levels from courses
$levels = $conn->query("SELECT DISTINCT level FROM courses ORDER BY level")->fetch_all(MYSQLI_ASSOC);

// Get all courses
$courses = $conn->query("SELECT course_code, course_name, level FROM courses ORDER BY level, course_code")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Timetable Management</title>
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
        
        /* Timetable Display */
        .timetable-level {
            margin-bottom: 3rem;
        }
        
        .level-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--dark);
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary);
        }
        
        .timetable-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
            background-color: var(--white);
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .timetable-table th {
            background-color: var(--primary);
            color: var(--white);
            padding: 1rem;
            text-align: center;
            font-weight: 500;
        }
        
        .timetable-table td {
            padding: 1rem;
            border: 1px solid var(--gray-light);
            vertical-align: top;
        }
        
        .timetable-slot {
            background-color: rgba(67, 97, 238, 0.1);
            border-radius: var(--border-radius);
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .timetable-slot:last-child {
            margin-bottom: 0;
        }
        
        .timetable-slot h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
            color: var(--primary-dark);
        }
        
        .timetable-slot p {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }
        
        .timetable-slot .actions {
            margin-top: 0.5rem;
            display: flex;
            gap: 0.5rem;
        }
        
        .timetable-slot .actions .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-container, .container {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .timetable-table {
                display: block;
                overflow-x: auto;
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

        <!-- Add Timetable Entry Form -->
        <div class="card">
            <h2 class="card-title">Add New Timetable Entry</h2>
            <form method="POST" id="addTimetableForm">
                <input type="hidden" name="add_timetable" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Level</label>
                        <select name="level" id="levelSelect" class="form-control" required>
                            <option value="">Select Level</option>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?php echo htmlspecialchars($level['level']); ?>">
                                    Level <?php echo htmlspecialchars($level['level']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course</label>
                        <select name="course_code" id="courseSelect" class="form-control" required>
                            <option value="">Select Course</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Day of Week</label>
                        <select name="day_of_week" class="form-control" required>
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Start Time</label>
                        <input type="time" name="start_time" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Time</label>
                        <input type="time" name="end_time" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Venue</label>
                        <input type="text" name="venue" class="form-control" required>
                    </div>
                </div>
                <div class="form-group" style="text-align: right; margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">Add Timetable Entry</button>
                </div>
            </form>
        </div>

        <!-- View Timetable by Level -->
        <div class="card">
            <h2 class="card-title">View Timetable by Level</h2>
            <div class="form-group">
                <label class="form-label">Select Level</label>
                <select id="viewLevelSelect" class="form-control">
                    <option value="">Select Level</option>
                    <?php foreach ($levels as $level): ?>
                        <option value="<?php echo htmlspecialchars($level['level']); ?>">
                            Level <?php echo htmlspecialchars($level['level']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="timetableView" style="margin-top: 1.5rem;">
                <table class="timetable-table" id="viewTimetableTable" style="display: none;">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Monday</th>
                            <th>Tuesday</th>
                            <th>Wednesday</th>
                            <th>Thursday</th>
                            <th>Friday</th>
                            <th>Saturday</th>
                            <th>Sunday</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Populate courses based on selected level for Add Timetable form
        document.getElementById('levelSelect').addEventListener('change', function() {
            const level = this.value;
            const courseSelect = document.getElementById('courseSelect');
            const courses = <?php echo json_encode($courses); ?>;
            
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            if (level) {
                courses.forEach(course => {
                    if (course.level === level) {
                        const option = document.createElement('option');
                        option.value = course.course_code;
                        option.textContent = `${course.course_code} - ${course.course_name}`;
                        courseSelect.appendChild(option);
                    }
                });
            }
        });

        // Fetch and display timetable for selected level
        document.getElementById('viewLevelSelect').addEventListener('change', function() {
            const level = this.value;
            const table = document.getElementById('viewTimetableTable');
            const tbody = table.querySelector('tbody');
            tbody.innerHTML = '';
            
            if (!level) {
                table.style.display = 'none';
                return;
            }

            fetch(`api/timetable.php?level=${encodeURIComponent(level)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.entries) {
                        const startHour = 8;
                        const endHour = 18;
                        
                        for (let hour = startHour; hour <= endHour; hour++) {
                            const row = document.createElement('tr');
                            row.innerHTML = `<td>${hour.toString().padStart(2, '0')}:00</td>`;
                            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            
                            days.forEach(day => {
                                let cellContent = '';
                                data.entries.forEach(entry => {
                                    const start = new Date(`1970-01-01T${entry.start_time}Z`);
                                    const end = new Date(`1970-01-01T${entry.end_time}Z`);
                                    const current = new Date(`1970-01-01T${hour.toString().padStart(2, '0')}:00:00Z`);
                                    
                                    if (entry.day_of_week === day && current >= start && current < end) {
                                        cellContent += `
                                            <div class="timetable-slot">
                                                <h4>${entry.course_code} - ${entry.course_name}</h4>
                                                <p>${start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} - ${end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</p>
                                                <p>${entry.venue}</p>
                                                ${entry.lecturer_name ? `<p>Lecturer: ${entry.lecturer_name}</p>` : ''}
                                                <div class="actions">
                                                    <a href="?delete=${entry.id}" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this entry?')">Delete</a>
                                                </div>
                                            </div>
                                        `;
                                    }
                                });
                                row.innerHTML += `<td>${cellContent}</td>`;
                            });
                            
                            tbody.appendChild(row);
                        }
                        
                        table.style.display = 'table';
                    } else {
                        table.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error fetching timetable:', error);
                    table.style.display = 'none';
                });
        });
    </script>
</body>
</html>