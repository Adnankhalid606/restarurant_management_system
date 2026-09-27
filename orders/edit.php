<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($id <= 0) {
    die("Invalid order ID.");
}


// Get order

$sql = "SELECT *
        FROM orders
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$order = mysqli_fetch_assoc($result);

if (!$order) {
    die("Order not found.");
}


// Check ownership

if ($_SESSION["role"] === "waiter") {

    if ((int) $order["waiter_id"] !== (int) $_SESSION["user_id"]) {
        die("Access denied.");
    }
}


// Get customers

$customers = mysqli_query(
    $conn,
    "SELECT id, name
     FROM customers
     ORDER BY name ASC"
);


// Get tables

$tables = mysqli_query(
    $conn,
    "SELECT id, table_number
     FROM restaurant_tables
     ORDER BY table_number ASC"
);


// Get waiters

$waiters = mysqli_query(
    $conn,
    "SELECT id, name
     FROM users
     WHERE role = 'waiter'
     AND is_active = 1
     ORDER BY name ASC"
);


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customer_id = !empty($_POST["customer_id"])
        ? (int) $_POST["customer_id"]
        : null;

    $table_id = !empty($_POST["table_id"])
        ? (int) $_POST["table_id"]
        : null;

    $order_type = $_POST["order_type"] ?? "";
    $status = $_POST["status"] ?? "";
    


    // Set waiter

    if ($_SESSION["role"] === "waiter") {

        $waiter_id = (int) $_SESSION["user_id"];

    } else {

        $waiter_id = !empty($_POST["waiter_id"])
            ? (int) $_POST["waiter_id"]
            : 0;

        if ($waiter_id <= 0) {
            die("Please select a waiter.");
        }
    }


    // Check waiter

    $sql = "SELECT id
            FROM users
            WHERE id = ?
            AND role = 'waiter'
            AND is_active = 1";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $waiter_id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        die("Invalid waiter.");
    }


    // Validate order type

    $allowed_order_types = [
        "dine_in",
        "delivery",
        "pickup"
    ];

    if (!in_array($order_type, $allowed_order_types)) {
        die("Invalid order type.");
    }


    // Validate status

    $allowed_statuses = [
        "pending",
        "preparing",
        "ready",
        "cancelled"
    ];

    if (!in_array($status, $allowed_statuses)) {
        die("Invalid status.");
    }



    // Remove table if needed

    if ($order_type !== "dine_in") {
        $table_id = null;
    }

    // Get old table

$old_table_id = !empty($order["table_id"])
    ? (int) $order["table_id"]
    : null;

$new_table_id = $table_id;







    mysqli_begin_transaction($conn);

try {

    if ($status === "cancelled") {

        // Release old table if assigned
        if ($old_table_id !== null) {

            $sql = "UPDATE restaurant_tables
                    SET status = 'available'
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $old_table_id
            );

            mysqli_stmt_execute($stmt);
        }

    } else {

        // Check new table

        if (
            $new_table_id !== null &&
            $new_table_id !== $old_table_id
        ) {

            $sql = "SELECT status
                    FROM restaurant_tables
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $new_table_id
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            $new_table = mysqli_fetch_assoc($result);

            if (!$new_table) {
                throw new Exception("Table not found.");
            }

            if ($new_table["status"] !== "available") {
                throw new Exception("Selected table is not available.");
            }
        }


        // Release old table

        if (
            $old_table_id !== null &&
            $old_table_id !== $new_table_id
        ) {

            $sql = "UPDATE restaurant_tables
                    SET status = 'available'
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $old_table_id
            );

            mysqli_stmt_execute($stmt);
        }


        // Occupy new table

        if (
            $new_table_id !== null &&
            $new_table_id !== $old_table_id
        ) {

            $sql = "UPDATE restaurant_tables
                    SET status = 'occupied'
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $new_table_id
            );

            mysqli_stmt_execute($stmt);
        }
    }


    // Update order

    $sql = "UPDATE orders
            SET customer_id = ?,
                table_id = ?,
                waiter_id = ?,
                order_type = ?,
                status = ?
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "iiissi",
        $customer_id,
        $table_id,
        $waiter_id,
        $order_type,
        $status,
        $id
    );

    mysqli_stmt_execute($stmt);


    mysqli_commit($conn);

    header("Location: view.php?id=" . $id);
    exit;

} catch (Exception $error) {

    mysqli_rollback($conn);

    die($error->getMessage());
}
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Edit Order</title>

</head>

<body>

    <h1>
        Edit Order #<?php echo $order["id"]; ?>
    </h1>


    <form method="POST">

        <label>
            Customer
        </label>

        <br>

        <select name="customer_id">

            <option value="">
                Walk-in Customer
            </option>

            <?php while ($customer = mysqli_fetch_assoc($customers)) { ?>

                <option value="<?php echo $customer["id"]; ?>" <?php
                   if ($customer["id"] == $order["customer_id"]) {
                       echo "selected";
                   }
                   ?>>

                    <?php echo htmlspecialchars($customer["name"]); ?>

                </option>

            <?php } ?>

        </select>

        <br><br>


        <?php if ($_SESSION["role"] === "admin") { ?>

            <label>
                Waiter
            </label>

            <br>

            <select name="waiter_id" required>

                <option value="">
                    Select Waiter
                </option>

                <?php while ($waiter = mysqli_fetch_assoc($waiters)) { ?>

                    <option value="<?php echo $waiter["id"]; ?>" <?php
                       if ($waiter["id"] == $order["waiter_id"]) {
                           echo "selected";
                       }
                       ?>>

                        <?php echo $waiter["name"]; ?>

                    </option>

                <?php } ?>

            </select>

            <br><br>

        <?php } else { ?>

            <p>

                <strong>
                    Waiter:
                </strong>

                <?php echo $_SESSION["user_name"]; ?>

            </p>

        <?php } ?>


        <label>
            Table
        </label>

        <br>

        <select name="table_id">

            <option value="">
                No Table
            </option>

            <?php while ($table = mysqli_fetch_assoc($tables)) { ?>

                <option value="<?php echo $table["id"]; ?>" <?php
                   if ($table["id"] == $order["table_id"]) {
                       echo "selected";
                   }
                   ?>>

                    Table
                    <?php echo $table["table_number"]; ?>

                </option>

            <?php } ?>

        </select>

        <br><br>


        <label>
            Order Type
        </label>

        <br>

        <select name="order_type">

            <option value="dine_in" <?php
            if ($order["order_type"] === "dine_in") {
                echo "selected";
            }
            ?>>
                Dine In
            </option>

            <option value="delivery" <?php
            if ($order["order_type"] === "delivery") {
                echo "selected";
            }
            ?>>
                Delivery
            </option>

            <option value="pickup" <?php
            if ($order["order_type"] === "pickup") {
                echo "selected";
            }
            ?>>
                Pickup
            </option>

        </select>

        <br><br>


        <label>
            Status
        </label>

        <br>

        <select name="status">

            <option value="pending" <?php
            if ($order["status"] === "pending") {
                echo "selected";
            }
            ?>>
                Pending
            </option>

            <option value="preparing" <?php
            if ($order["status"] === "preparing") {
                echo "selected";
            }
            ?>>
                Preparing
            </option>

            <option value="ready" <?php
            if ($order["status"] === "ready") {
                echo "selected";
            }
            ?>>
                Ready
            </option>

            <option value="cancelled" <?php
            if ($order["status"] === "cancelled") {
                echo "selected";
            }
            ?>>
                Cancelled
            </option>


        </select>

        <br><br>

        <br><br>


        <button type="submit">
            Update Order
        </button>

    </form>


    <br>

    <a href="view.php?id=<?php echo $id; ?>">
        Back to Order
    </a>

</body>

</html>