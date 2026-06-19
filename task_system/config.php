<?php

$host = "localhost";
$user = "root";
$pass = "";
$db   = "task_system";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Greška konekcije: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>