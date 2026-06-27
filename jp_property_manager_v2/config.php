<?php
// JP Property Manager v2 - konfiguracija baze
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'jp_properties';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Greška pri povezivanju sa bazom: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
