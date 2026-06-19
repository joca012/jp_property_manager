<?php
include "config.php";

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {

    $conn->query("
        UPDATE tasks
        SET status='zakazano'
        WHERE id=$id
        AND status='zavrseno'
    ");
}

header("Location: index.php");
exit;