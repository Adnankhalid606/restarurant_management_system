<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$id = $_GET["id"];

$sql = "SELECT
            bills.id,
            bills.order_id,
            bills.subtotal,
            bills.discount,
            bills.tax,
            bills.total_amount,
            bills.payment_status,
            bills.paid_at,
            bills.created_at,
            orders.order_type,
            orders.status AS order_status,
            orders.created_at AS order_created_at,
            customers.name AS customer_name,
            customers.phone AS customer_phone
        FROM bills
        JOIN orders ON bills.order_id = orders.id
        LEFT JOIN customers ON orders.customer_id = customers.id
        WHERE bills.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$bill = mysqli_fetch_assoc($result);

if (!$bill) {
    die("Bill not found.");
}


// Get order items
$sql = "SELECT
            order_items.quantity,
            order_items.unit_price,
            order_items.subtotal,
            menu_items.name AS menu_item_name
        FROM order_items
        JOIN menu_items ON order_items.menu_item_id = menu_items.id
        WHERE order_items.order_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $bill["order_id"]);
mysqli_stmt_execute($stmt);

$items = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Bill #<?php echo $bill["id"]; ?></title>
</head>

<body>

<h1>Bill #<?php echo $bill["id"]; ?></h1>

<h2>Customer Information</h2>

<p>
    <strong>Customer:</strong>
    <?php echo $bill["customer_name"] ?? "Walk-in"; ?>
</p>

<p>
    <strong>Phone:</strong>
    <?php echo $bill["customer_phone"] ?? "-"; ?>
</p>

<p>
    <strong>Order Type:</strong>
    <?php echo $bill["order_type"]; ?>
</p>

<p>
    <strong>Order Status:</strong>
    <?php echo $bill["order_status"]; ?>
</p>

<p>
    <strong>Order ID:</strong>
    <?php echo $bill["order_id"]; ?>
</p>


<h2>Order Items</h2>

<table border="1" cellpadding="10">

<tr>
    <th>Item</th>
    <th>Quantity</th>
    <th>Unit Price</th>
    <th>Subtotal</th>
</tr>

<?php while ($item = mysqli_fetch_assoc($items)) { ?>

<tr>

    <td>
        <?php echo $item["menu_item_name"]; ?>
    </td>

    <td>
        <?php echo $item["quantity"]; ?>
    </td>

    <td>
        <?php echo $item["unit_price"]; ?>
    </td>

    <td>
        <?php echo $item["subtotal"]; ?>
    </td>

</tr>

<?php } ?>

</table>


<h2>Bill Summary</h2>

<p>
    <strong>Subtotal:</strong>
    <?php echo $bill["subtotal"]; ?>
</p>

<p>
    <strong>Discount:</strong>
    <?php echo $bill["discount"]; ?>
</p>

<p>
    <strong>Tax:</strong>
    <?php echo $bill["tax"]; ?>
</p>

<p>
    <strong>Total:</strong>
    <?php echo $bill["total_amount"]; ?>
</p>

<p>
    <strong>Payment Status:</strong>
    <?php echo $bill["payment_status"]; ?>
</p>

<?php if ($bill["paid_at"] !== null) { ?>

<p>
    <strong>Paid At:</strong>
    <?php echo $bill["paid_at"]; ?>
</p>

<?php } ?>


<br>

<?php if ($bill["payment_status"] === "unpaid") { ?>

<a href="pay.php?id=<?php echo $bill["id"]; ?>">
    Mark Bill as Paid
</a>

<br><br>

<?php } ?>


<a href="index.php">
    Back to Bills
</a>

</body>

</html>