<?php

require_once "../config/database.php";

$sql = "SELECT
            bills.id,
            bills.order_id,
            bills.subtotal,
            bills.discount,
            bills.tax,
            bills.total_amount,
            bills.payment_status,
            bills.paid_at,
            bills.created_at

        FROM bills

        ORDER BY bills.id DESC";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Bills</title>
</head>

<body>

    <h1>Bills</h1>

    <a href="create.php">
        Create Bill
    </a>

    <br><br>

    <table border="1" cellpadding="10">

        <tr>
            <th>ID</th>
            <th>Order ID</th>
            <th>Subtotal</th>
            <th>Discount</th>
            <th>Tax</th>
            <th>Total</th>
            <th>Payment Status</th>
            <th>Paid At</th>
            <th>Actions</th>
        </tr>

        <?php while ($bill = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    <?php echo $bill["id"]; ?>
                </td>

                <td>
                    <?php echo $bill["order_id"]; ?>
                </td>

                <td>
                    <?php echo $bill["subtotal"]; ?>
                </td>

                <td>
                    <?php echo $bill["discount"]; ?>
                </td>

                <td>
                    <?php echo $bill["tax"]; ?>
                </td>

                <td>
                    <?php echo $bill["total_amount"]; ?>
                </td>

                <td>
                    <?php echo $bill["payment_status"]; ?>
                </td>

                <td>
                    <?php echo $bill["paid_at"] ?? "-"; ?>
                </td>

                <td>

                    <a href="view.php?id=<?php echo $bill["id"]; ?>">
                        View
                    </a>

                    |

                    <?php if ($bill["payment_status"] === "unpaid") { ?>

                        <a href="pay.php?id=<?php echo $bill["id"]; ?>">
                            Pay
                        </a>

                        |

                    <?php } ?>

                    <a href="delete.php?id=<?php echo $bill["id"]; ?>">
                        Delete
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>