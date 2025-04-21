<?php include 'db.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Add User</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef;
            padding: 20px;
        }
        form {
            background: white;
            padding: 20px;
            border-radius: 10px;
            width: 350px;
            margin: auto;
        }
        input, select, button {
            width: 100%;
            padding: 8px;
            margin-top: 10px;
        }
        h2 {
            text-align: center;
        }
    </style>
</head>
<body>
    <form method="post" action="">
        <h2>Add New User</h2>
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email (must end with @central.edu.gh)" required>
        <select name="role" required>
            <option value="">-- Select Role --</option>
            <option value="student">Student</option>
            <option value="lecturer">Lecturer</option>
        </select>
        <button type="submit" name="add_user">Add User</button>
    </form>

    <?php
    if (isset($_POST['add_user'])) {
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $role = $_POST['role'];

        // Check email domain
        if (!str_ends_with($email, '@central.edu.gh')) {
            echo "<p style='color:red; text-align:center;'>Email must end with @central.edu.gh</p>";
            exit;
        }

        // Insert user with no password and not verified yet
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $full_name, $email, $role);
        if ($stmt->execute()) {
            echo "<p style='color:green; text-align:center;'>User added successfully as <b>$role</b>. They'll set password after verification.</p>";
        } else {
            echo "<p style='color:red; text-align:center;'>Error: " . $stmt->error . "</p>";
        }
    }
    ?>
</body>
</html>
