<?php

function getStatusColor($status) {

    switch ($status) {

        case "zakazano":
            return "#ffc107";

        case "zavrseno":
            return "#28a745";

        case "propusteno":
            return "#dc3545";

        case "todo":
            return "#dc3545";

        case "obrisano":
            return "#6c757d";

        default:
            return "#6c757d";
    }
}


function autoUpdateStatus($conn) {

    $now = date('Y-m-d H:i:s');

    $sql = "UPDATE tasks
            SET status = 'propusteno'
            WHERE status = 'zakazano'
            AND status != 'zavrseno'
            AND status != 'obrisano'
            AND CONCAT(datum, ' ', vreme) < '$now'";

    $conn->query($sql);
}


function renderActions($row) {

    $dugme = "";

    if ($row['status'] != "zavrseno") {
        $dugme .= "<br><br><a href='zavrsi.php?id={$row['id']}'>✔ Završi</a>";
    }

    if ($row['status'] != "zavrseno" && $row['status'] != "todo") {
        $dugme .= " | <a href='otkazi.php?id={$row['id']}'>✖ Otkaži</a>";
    }

    return $dugme;
}


function deleteTask($conn, $id) {

    $id = (int)$id;

    if ($id <= 0) {
        return false;
    }

    $sql = "UPDATE tasks
            SET status = 'obrisano'
            WHERE id = $id";

    return $conn->query($sql);
}