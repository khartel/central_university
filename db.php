<?php
$host = 'localhost';
$user = 'root';
$password = 'password';
$database = 'central_university';

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
