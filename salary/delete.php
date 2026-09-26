<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

if ($_SESSION["role"] !== "admin") {
    die("Access denied.");
}

if (!isset($_GET["id"])) {
    die("Salary ID is required.");
}

$salary_id = (int) $_GET["id"];

// Check salary
$sql = "
    SELECT id
    FROM salaries
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $salary_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    die("Salary record not found.");
}

// Delete salary
$sql = "
    DELETE FROM salaries
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $salary_id);
mysqli_stmt_execute($stmt);

header("Location: index.php");
exit;