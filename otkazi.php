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

/* Povratak na stranicu sa koje je pozvano */
$return = $_GET['return'] ?? 'index.php';

header("Location: " . $return);
exit;