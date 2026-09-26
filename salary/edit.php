<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

if ($_SESSION["role"] !== "admin") {
    die("Access denied.");
}

if (!isset($_GET["id"])) {
    die("Salary ID is required.");
}

$salary_id = (int) $_GET["id"];

// Get salary
$sql = "
    SELECT
        id,
        user_id,
        amount,
        salary_type,
        salary_date,
        payment_status,
        description
    FROM salaries
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $salary_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$salary = mysqli_fetch_assoc($result);

if (!$salary) {
    die("Salary record not found.");
}

// Get employees
$sql = "
    SELECT
        id,
        name,
        role
    FROM users
    WHERE role != 'admin'
    AND is_active = 1
    ORDER BY name ASC
";

$employees_result = mysqli_query($conn, $sql);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $user_id = (int) $_POST["user_id"];
    $amount = (float) $_POST["amount"];
    $salary_type = $_POST["salary_type"];
    $salary_date = $_POST["salary_date"];
    $payment_status = $_POST["payment_status"];
    $description = trim($_POST["description"]);

    // Validate salary type
    if (!in_array($salary_type, ["daily", "monthly"])) {
        die("Invalid salary type.");
    }

    // Validate payment status
    if (!in_array($payment_status, ["unpaid", "paid"])) {
        die("Invalid payment status.");
    }

    // Validate amount
    if ($amount <= 0) {
        die("Salary amount must be greater than zero.");
    }

    // Check employee
    $sql = "
        SELECT id
        FROM users
        WHERE id = ?
        AND role != 'admin'
        AND is_active = 1
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);

    $employee_result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($employee_result) === 0) {
        die("Invalid employee.");
    }

    // Update salary
    $sql = "
        UPDATE salaries
        SET
            user_id = ?,
            amount = ?,
            salary_type = ?,
            salary_date = ?,
            payment_status = ?,
            description = ?
        WHERE id = ?
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "idssssi",
        $user_id,
        $amount,
        $salary_type,
        $salary_date,
        $payment_status,
        $description,
        $salary_id
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Salary</title>
</head>

<body>

    <h1>Edit Salary</h1>

    <a href="index.php">Back to Salary Management</a>

    <hr>

    <form method="POST">

        <label>
            Employee:
        </label>

        <select name="user_id" required>

            <?php while ($row = mysqli_fetch_assoc($employees_result)) { ?>

                <option
                    value="<?php echo $row["id"]; ?>"
                    <?php echo $row["id"] == $salary["user_id"] ? "selected" : ""; ?>
                >

                    <?php echo htmlspecialchars($row["name"]); ?>

                    -
                    <?php echo ucfirst($row["role"]); ?>

                </option>

            <?php } ?>

        </select>

        <br><br>

        <label>
            Amount:
        </label>

        <input
            type="number"
            name="amount"
            step="0.01"
            min="0"
            value="<?php echo htmlspecialchars($salary["amount"]); ?>"
            required
        >

        <br><br>

        <label>
            Salary Type:
        </label>

        <select name="salary_type" required>

            <option
                value="daily"
                <?php echo $salary["salary_type"] === "daily" ? "selected" : ""; ?>
            >
                Daily
            </option>

            <option
                value="monthly"
                <?php echo $salary["salary_type"] === "monthly" ? "selected" : ""; ?>
            >
                Monthly
            </option>

        </select>

        <br><br>

        <label>
            Salary Date:
        </label>

        <input
            type="date"
            name="salary_date"
            value="<?php echo $salary["salary_date"]; ?>"
            required
        >

        <br><br>

        <label>
            Payment Status:
        </label>

        <select name="payment_status" required>

            <option
                value="unpaid"
                <?php echo $salary["payment_status"] === "unpaid" ? "selected" : ""; ?>
            >
                Unpaid
            </option>

            <option
                value="paid"
                <?php echo $salary["payment_status"] === "paid" ? "selected" : ""; ?>
            >
                Paid
            </option>

        </select>

        <br><br>

        <label>
            Description:
        </label>

        <br>

        <textarea
            name="description"
            rows="4"
            cols="40"
            placeholder="Optional description"
        ><?php echo htmlspecialchars($salary["description"] ?? ""); ?></textarea>

        <br><br>

        <button type="submit">
            Update Salary
        </button>

    </form>

</body>

</html>