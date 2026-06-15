<?php
include "config.php";

$id = $_GET['id'] ?? 0;

if ($id > 0) {

    $sql = "UPDATE tasks 
            SET status='todo',
                datum=NULL,
                vreme=NULL
            WHERE id=$id";

    $conn->query($sql);
}

header("Location: index.php");
exit;