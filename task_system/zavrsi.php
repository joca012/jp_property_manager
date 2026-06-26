<?php
include "config.php";

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $sql = "UPDATE tasks
            SET status='zavrseno'
            WHERE id=$id";

    $conn->query($sql);
}

$return = $_GET['return'] ?? 'index.php';

if (preg_match('/^(https?:)?\/\//i', $return) || preg_match('/[\r\n]/', $return)) {
    $return = 'index.php';
}

header("Location: " . $return);
exit;
