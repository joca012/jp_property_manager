<?php
include "config.php";

$id = (int)($_GET['id'] ?? 0);
$sz_id = (int)($_GET['sz_id'] ?? 0);

if ($id <= 0 || $sz_id <= 0) {
    die("Neispravan zahtev.");
}

$stmt = $conn->prepare("
    UPDATE oprema_zgrade
    SET aktivna = 0
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: oprema.php?sz_id=" . $sz_id);
exit;