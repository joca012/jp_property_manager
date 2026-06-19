<?php
include "config.php";
include "functions.php";

$id = $_GET['id'] ?? 0;

deleteTask($conn, $id);

header("Location: index.php");
exit;