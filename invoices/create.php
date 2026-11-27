<?php
/**
 * Invoices are automatically generated when orders transition to 'ready' or 'completed'.
 * Manual invoice creation is disabled by system policy.
 */

require_once "../includes/helpers.php";
start_secure_session();

$_SESSION["info"] = "Invoices are generated automatically when orders are marked as Ready or Completed.";
header("Location: index.php", true, 302);
exit;
