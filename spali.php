<?php
include "config.php";

$id = $_GET['id'] ?? 0;
$id = (int)$id;

if ($id > 0) {

    $sql = "DELETE FROM tasks
            WHERE id = $id
              AND status = 'obrisano'";

    $conn->query($sql);
}

header("Location: recycle.php");
exit;