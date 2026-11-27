<?php
// Transparent compatibility redirect to modern Invoices module
$id = (int) ($_GET["id"] ?? 0);
$url = "../invoices/delete.php" . ($id > 0 ? "?id=" . $id : "");
header("Location: " . $url, true, 302);
exit;
