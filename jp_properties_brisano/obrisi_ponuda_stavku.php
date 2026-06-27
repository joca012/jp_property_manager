<?php
include "config.php";

$id = (int)($_GET['id'] ?? 0);
$ponuda_id = (int)($_GET['ponuda_id'] ?? 0);

if ($id <= 0 || $ponuda_id <= 0) {
    die("Neispravan zahtev.");
}

$stmt = $conn->prepare("
    UPDATE ponuda_stavke
    SET aktivna = 0
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: ponuda_stavke.php?ponuda_id=" . $ponuda_id);
exit;
