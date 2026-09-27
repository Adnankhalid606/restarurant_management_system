<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$id = $_GET["id"];

$sql = "SELECT
            reservations.id,
            customers.name AS customer_name,
            customers.phone AS customer_phone,
            restaurant_tables.table_number,
            restaurant_tables.capacity,
            reservations.reservation_date,
            reservations.reservation_time,
            reservations.reservation_end_time,
            reservations.guests,
            reservations.status,
            reservations.created_at

        FROM reservations

        JOIN customers
            ON reservations.customer_id = customers.id

        JOIN restaurant_tables
            ON reservations.table_id = restaurant_tables.id

        WHERE reservations.id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$reservation = mysqli_fetch_assoc($result);

if (!$reservation) {
    die("Reservation not found.");
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Reservation Details</title>
</head>

<body>

    <h1>
        Reservation #<?php echo $reservation["id"]; ?>
    </h1>

    <p>
        <strong>Customer:</strong>
        <?php echo $reservation["customer_name"]; ?>
    </p>

    <p>
        <strong>Phone:</strong>
        <?php echo $reservation["customer_phone"]; ?>
    </p>

    <p>
        <strong>Table:</strong>
        Table <?php echo $reservation["table_number"]; ?>
    </p>

    <p>
        <strong>Table Capacity:</strong>
        <?php echo $reservation["capacity"]; ?>
    </p>

    <p>
        <strong>Date:</strong>
        <?php echo $reservation["reservation_date"]; ?>
    </p>

    <p>
        <strong>Start Time:</strong>
        <?php echo $reservation["reservation_time"]; ?>
    </p>

    <p>
        <strong>End Time:</strong>
        <?php echo $reservation["reservation_end_time"]; ?>
    </p>

    <p>
        <strong>Guests:</strong>
        <?php echo $reservation["guests"]; ?>
    </p>

    <p>
        <strong>Status:</strong>
        <?php echo $reservation["status"]; ?>
    </p>

    <p>
        <strong>Created:</strong>
        <?php echo $reservation["created_at"]; ?>
    </p>

    <br>

    <a href="edit.php?id=<?php echo $reservation["id"]; ?>">
        Edit Reservation
    </a>

    <br><br>

    <a href="index.php">
        Back to Reservations
    </a>

</body>

</html>