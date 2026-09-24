<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "restaurant_pos";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    echo "Database not connected" . mysqli_connect_error();
};
