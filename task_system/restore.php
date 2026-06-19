<?php
include "config.php";

$id = $_GET['id'] ?? 0;
$id = (int)$id;

if ($id > 0) {

    $sql = "UPDATE tasks
            SET status = 'todo',
                datum = NULL,
                vreme = NULL
            WHERE id = $id
              AND status = 'obrisano'";

    $conn->query($sql);
}

header("Location: recycle.php");
exit;