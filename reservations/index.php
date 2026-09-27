<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$sql = "SELECT
            reservations.id,
            customers.name AS customer_name,
            restaurant_tables.table_number,
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

        ORDER BY
            reservations.reservation_date ASC,
            reservations.reservation_time ASC";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Reservations</title>
</head>

<body>

    <h1>Reservations</h1>

    <a href="create.php">
        Create Reservation
    </a>

    <br><br>

    <table border="1" cellpadding="10">

        <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Table</th>
            <th>Date</th>
            <th>Start Time</th>
            <th>End Time</th>
            <th>Guests</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php while ($reservation = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    <?php echo $reservation["id"]; ?>
                </td>

                <td>
                    <?php echo $reservation["customer_name"]; ?>
                </td>

                <td>
                    Table <?php echo $reservation["table_number"]; ?>
                </td>

                <td>
                    <?php echo $reservation["reservation_date"]; ?>
                </td>

                <td>
                    <?php echo $reservation["reservation_time"]; ?>
                </td>

                <td>
                    <?php echo $reservation["reservation_end_time"]; ?>
                </td>

                <td>
                    <?php echo $reservation["guests"]; ?>
                </td>

                <td>
                    <?php echo $reservation["status"]; ?>
                </td>

                <td>

                    <a href="view.php?id=<?php echo $reservation["id"]; ?>">
                        View
                    </a>

                    |

                    <a href="edit.php?id=<?php echo $reservation["id"]; ?>">
                        Edit
                    </a>

                    |

                    <a href="delete.php?id=<?php echo $reservation["id"]; ?>">
                        Delete
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>