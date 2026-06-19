<?php
include "config.php";

$id = (int)($_GET['id'] ?? 0);
$budzet_id = (int)($_GET['budzet_id'] ?? 0);

if ($id <= 0 || $budzet_id <= 0) {
    die("Neispravan zahtev.");
}

$stmt = $conn->prepare("
    UPDATE budzet_stavke
    SET aktivna = 0
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: budzet_stavke.php?budzet_id=" . $budzet_id);
exit;