<?php
include "config.php";

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Neispravan zahtev.");
}

$stmt = $conn->prepare("
    UPDATE izvodjaci
    SET aktivan = 0
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: izvodjaci.php");
exit;
