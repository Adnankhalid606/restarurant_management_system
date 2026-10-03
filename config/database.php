<?php
date_default_timezone_set('Asia/Karachi');

$host = "localhost";
$username = "root";
$password = "";
$database = "restaurant_pos";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET time_zone = '+05:00'");
