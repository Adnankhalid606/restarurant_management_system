<?php

require_once "../includes/role.php";

require_once "../config/database.php";
requireRole(["admin"]);

$sql = "SELECT id, name, email, role, is_active, created_at
        FROM users
        ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Users</title>
</head>

<body>

<h1>Users</h1>

<a href="create.php">Create User</a>

<br><br>

<table border="1" cellpadding="10">

<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Email</th>
    <th>Role</th>
    <th>Status</th>
    <th>Created</th>
    <th>Actions</th>
</tr>

<?php while ($user = mysqli_fetch_assoc($result)) { ?>

<tr>

    <td>
        <?php echo $user["id"]; ?>
    </td>

    <td>
        <?php echo $user["name"]; ?>
    </td>

    <td>
        <?php echo $user["email"]; ?>
    </td>

    <td>
        <?php echo $user["role"]; ?>
    </td>

    <td>
        <?php echo $user["is_active"] ? "Active" : "Inactive"; ?>
    </td>

    <td>
        <?php echo $user["created_at"]; ?>
    </td>

    <td>

        <a href="edit.php?id=<?php echo $user["id"]; ?>">
            Edit
        </a>

        |

        <a href="delete.php?id=<?php echo $user["id"]; ?>">
            Delete
        </a>

    </td>

</tr>

<?php } ?>

</table>

<br>

<a href="../dashboard/index.php">
    Back to Dashboard
</a>

</body>

</html>