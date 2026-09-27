<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($id <= 0) {
    die("Invalid purchase ID.");
}


// Get purchase

$sql = "SELECT
            id,
            supplier_id,
            total_amount,
            payment_status
        FROM purchases
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$purchase = mysqli_fetch_assoc($result);

if (!$purchase) {
    die("Purchase not found.");
}


// Check payment

if ($purchase["payment_status"] === "paid") {
    die("Purchase is already paid.");
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    mysqli_begin_transaction($conn);

    try {

        // Lock purchase

        $sql = "SELECT
                    id,
                    payment_status
                FROM purchases
                WHERE id = ?
                FOR UPDATE";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $purchase = mysqli_fetch_assoc($result);

        if (!$purchase) {
            throw new Exception("Purchase not found.");
        }


        // Check payment

        if ($purchase["payment_status"] === "paid") {
            throw new Exception("Purchase is already paid.");
        }


        // Mark paid

        $sql = "UPDATE purchases
                SET payment_status = 'paid'
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "i",
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

    <title>Pay Purchase</title>

</head>

<body>

    <h1>
        Pay Purchase #<?php echo $purchase["id"]; ?>
    </h1>

    <p>
        Amount:
        <strong>
            Rs. <?php echo number_format($purchase["total_amount"], 2); ?>
        </strong>
    </p>

    <p>
        Current Status:
        <strong>
            <?php echo ucfirst($purchase["payment_status"]); ?>
        </strong>
    </p>


    <form method="POST">

        <p>
            Are you sure you want to mark this purchase as paid?
        </p>

        <button type="submit">
            Confirm Payment
        </button>

        <a href="view.php?id=<?php echo $id; ?>">
            Cancel
        </a>

    </form>

</body>

</html>