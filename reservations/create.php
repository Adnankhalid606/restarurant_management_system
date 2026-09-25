<?php

require_once "../config/database.php";

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
        die("Table not found.");
    }

    if ($guests > $table["capacity"]) {

        $error = "Number of guests is greater than table capacity.";

    } else {

        /*
         * Check overlapping reservation
         */

        $sql = "SELECT id
                FROM reservations

                WHERE table_id = ?

                AND reservation_date = ?

                AND status IN ('pending', 'confirmed')

                AND reservation_time < ?

                AND reservation_end_time > ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "isss",
            $table_id,
            $reservation_date,
            $reservation_end_time,
            $reservation_time
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {

            $error = "This table is already reserved during this time.";

        } else {

            /*
             * Create reservation
             */

            $sql = "INSERT INTO reservations
                    (
                        customer_id,
                        table_id,
                        reservation_date,
                        reservation_time,
                        reservation_end_time,
                        guests,
                        status
                    )

                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "iisssis",
                $customer_id,
                $table_id,
                $reservation_date,
                $reservation_time,
                $reservation_end_time,
                $guests,
                $status
            );

            mysqli_stmt_execute($stmt);

            header("Location: index.php");
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Create Reservation</title>
</head>

<body>

    <h1>Create Reservation</h1>

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

            <option value="">
                Select Customer
            </option>

            <?php while ($customer = mysqli_fetch_assoc($customers)) { ?>

                <option value="<?php echo $customer["id"]; ?>">
                    <?php echo $customer["name"]; ?>
                </option>

            <?php } ?>

        </select>

        <br><br>

        <label>Table</label>
        <br>

        <select name="table_id" required>

            <option value="">
                Select Table
            </option>

            <?php while ($table = mysqli_fetch_assoc($tables)) { ?>

                <option value="<?php echo $table["id"]; ?>">
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
            required
        >

        <br><br>

        <label>Start Time</label>
        <br>

        <input
            type="time"
            name="reservation_time"
            required
        >

        <br><br>

        <label>End Time</label>
        <br>

        <input
            type="time"
            name="reservation_end_time"
            required
        >

        <br><br>

        <label>Number of Guests</label>
        <br>

        <input
            type="number"
            name="guests"
            min="1"
            required
        >

        <br><br>

        <label>Status</label>
        <br>

        <select name="status">

            <option value="pending">
                Pending
            </option>

            <option value="confirmed">
                Confirmed
            </option>

            <option value="completed">
                Completed
            </option>

            <option value="cancelled">
                Cancelled
            </option>

        </select>

        <br><br>

        <button type="submit">
            Create Reservation
        </button>

    </form>

    <br>

    <a href="index.php">
        Back to Reservations
    </a>

</body>

</html>