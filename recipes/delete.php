<?php

require_once "../config/database.php";

$id = $_GET["id"];


$sql = "DELETE FROM recipe_items
        WHERE recipe_id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);


$sql = "DELETE FROM recipes
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);


header("Location: index.php");
exit;