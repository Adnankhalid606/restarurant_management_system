<?php

require_once "../config/database.php";

$id = $_GET["id"];

$sql = "SELECT *
        FROM reservations
        WHERE id = ?";

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

$customers = mysqli_query(
    $conn,
    "SELECT id, name
     FROM customers
     ORDER BY name ASC"
);

$tables = mysqli_query(
    $conn,
    "SELECT id, table_number, capacity
     FROM restaurant_tables
     ORDER BY table_number ASC"
);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customer_id = $_POST["customer_id"];
    $table_id = $_POST["table_id"];
    $reservation_date = $_POST["reservation_date"];
    $reservation_time = $_POST["reservation_time"];
    $reservation_end_time = $_POST["reservation_end_time"];
    $guests = $_POST["guests"];
    $status = $_POST["status"];

    /*
     * Check start and end time
     */

    if ($reservation_end_time <= $reservation_time) {

        $error = "End time must be after start time.";

    } else {

        /*
         * Check table capacity
         */

        $sql = "SELECT capacity
                FROM restaurant_tables
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $table_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $table = mysqli_fetch_assoc($result);

        if (!$table) {

            $error = "Table not found.";

        } elseif ($guests > $table["capacity"]) {

            $error = "Number of guests is greater than table capacity.";

        } else {

            /*
             * Check overlapping reservation
             *
             * Current reservation is excluded
             */

            $sql = "SELECT id
                    FROM reservations

                    WHERE table_id = ?

                    AND reservation_date = ?

                    AND status IN ('pending', 'confirmed')

                    AND id != ?

                    AND reservation_time < ?

                    AND reservation_end_time > ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "isiss",
                $table_id,
                $reservation_date,
                $id,
                $reservation_end_time,
                $reservation_time
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) > 0) {

                $error = "This table is already reserved during this time.";

            } else {

                /*
                 * Update reservation
                 */

                $sql = "UPDATE reservations

                        SET customer_id = ?,
                            table_id = ?,
                            reservation_date = ?,
                            reservation_time = ?,
                            reservation_end_time = ?,
                            guests = ?,
                            status = ?

                        WHERE id = ?";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "iisssisi",
                    $customer_id,
                    $table_id,
                    $reservation_date,
                    $reservation_time,
                    $reservation_end_time,
                    $guests,
                    $status,
                    $id
                );

                mysqli_stmt_execute($stmt);

                header("Location: index.php");
                exit;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Edit Reservation</title>
</head>

<body>

    <h1>Edit Reservation</h1>

    <?php if ($error !== "") { ?>

        <p>
            <strong>
                <?php echo $error; ?>
            </strong>
        </p>

    <?php } ?>

    <form method="POST">

        <label>Customer</label>
        <br>

        <select name="customer_id" required>

            <?php while ($customer = mysqli_fetch_assoc($customers)) { ?>

                <option
                    value="<?php echo $customer["id"]; ?>"
                    <?php
                    if ($customer["id"] == $reservation["customer_id"]) {
                        echo "selected";
                    }
                    ?>
                >
                    <?php echo $customer["name"]; ?>
                </option>

            <?php } ?>

        </select>

        <br><br>

        <label>Table</label>
        <br>

        <select name="table_id" required>

            <?php while ($table = mysqli_fetch_assoc($tables)) { ?>

                <option
                    value="<?php echo $table["id"]; ?>"
                    <?php
                    if ($table["id"] == $reservation["table_id"]) {
                        echo "selected";
                    }
                    ?>
                >
                    Table <?php echo $table["table_number"]; ?>
                    (Capacity: <?php echo $table["capacity"]; ?>)
                </option>

            <?php } ?>

        </select>

        <br><br>

        <label>Reservation Date</label>
        <br>

        <input
            type="date"
            name="reservation_date"
            value="<?php echo $reservation["reservation_date"]; ?>"
            required
        >

        <br><br>

        <label>Start Time</label>
        <br>

        <input
            type="time"
            name="reservation_time"
            value="<?php echo $reservation["reservation_time"]; ?>"
            required
        >

        <br><br>

        <label>End Time</label>
        <br>

        <input
            type="time"
            name="reservation_end_time"
            value="<?php echo $reservation["reservation_end_time"]; ?>"
            required
        >

        <br><br>

        <label>Number of Guests</label>
        <br>

        <input
            type="number"
            name="guests"
            min="1"
            value="<?php echo $reservation["guests"]; ?>"
            required
        >

        <br><br>

        <label>Status</label>
        <br>

        <select name="status">

            <option
                value="pending"
                <?php
                if ($reservation["status"] === "pending") {
                    echo "selected";
                }
                ?>
            >
                Pending
            </option>

            <option
                value="confirmed"
                <?php
                if ($reservation["status"] === "confirmed") {
                    echo "selected";
                }
                ?>
            >
                Confirmed
            </option>

            <option
                value="completed"
                <?php
                if ($reservation["status"] === "completed") {
                    echo "selected";
                }
                ?>
            >
                Completed
            </option>

            <option
                value="cancelled"
                <?php
                if ($reservation["status"] === "cancelled") {
                    echo "selected";
                }
                ?>
            >
                Cancelled
            </option>

        </select>

        <br><br>

        <button type="submit">
            Update Reservation
        </button>

    </form>

    <br>

    <a href="view.php?id=<?php echo $reservation["id"]; ?>">
        Back to Reservation
    </a>

</body>

</html>