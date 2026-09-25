<?php

require_once "../config/database.php";

$id = $_GET["id"];


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $status = $_POST["status"];


    $allowed_statuses = [
        "pending",
        "preparing",
        "ready"
    ];


    if (!in_array($status, $allowed_statuses)) {
        die("Invalid status.");
    }


    $sql = "UPDATE orders
            SET status = ?
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "si",
        $status,
        $id
    );

    mysqli_stmt_execute($stmt);


    header("Location: view.php?id=" . $id);
    exit;
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Update Kitchen Status</title>
</head>

<body>

    <h1>
        Update Kitchen Status
    </h1>

    <form method="POST">

        <label>Status</label>
        <br>

        <select name="status" required>

            <option value="pending">
                Pending
            </option>

            <option value="preparing">
                Preparing
            </option>

            <option value="ready">
                Ready
            </option>

        </select>

        <br><br>

        <button type="submit">
            Update Status
        </button>

    </form>

    <br>

    <a href="view.php?id=<?php echo $id; ?>">
        Back to Kitchen Order
    </a>

</body>

</html>