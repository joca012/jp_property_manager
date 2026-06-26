<?php
/* =========================
   BAZA
========================= */

$host = "localhost";
$user = "root";
$pass = "";
$db   = "task_system";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Greška konekcije: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

/* =========================
   SESSION
========================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   CENTRALNA LOGIN ZAŠTITA
   - Ne dodaje se auth.php u svaki fajl.
   - Svi fajlovi koji uključuju config.php automatski su zaštićeni.
========================= */

$currentFile = basename($_SERVER['PHP_SELF'] ?? '');

$publicFiles = [
    'login.php',
    'logout.php',
    'set_admin_password.php'
];

if (!in_array($currentFile, $publicFiles, true) && empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
