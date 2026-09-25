<?php

require_once "auth.php";

function requireRole($allowedRoles)
{
    if (!in_array($_SESSION["role"], $allowedRoles)) {
        die("Access denied.");
    }
}