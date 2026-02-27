<?php
// Process Logout
// Destroys the current session and redirects to the home page.

session_start();
$_SESSION = [];
session_destroy();
header("Location: ../index.php");
exit();
