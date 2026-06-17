<?php
include "config.php";

$id = $_POST['id'] ?? null;
$datum = $_POST['datum'] ?? null;
$vreme = $_POST['vreme'] ?? null;

if (!$id || !$datum || !$vreme) {
    echo "Greška: nedostaju podaci.";
    exit;
}

$id = (int)$id;
$datumSql = $conn->real_escape_string($datum);
$vremeSql = $conn->real_escape_string($vreme);

$sql = "
    UPDATE tasks
    SET datum = '$datumSql',
        vreme = '$vremeSql',
        status = 'zakazano'
    WHERE id = $id
";

if ($conn->query($sql)) {
    echo "OK";
} else {
    echo "Greška: " . $conn->error;
}