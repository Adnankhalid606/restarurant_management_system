<?php

require_once "../config/database.php";

$id = $_GET["id"];


$sql = "DELETE FROM raw_materials
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);


header("Location: materials.php");
exit;