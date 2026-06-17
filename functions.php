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

    $sql = "
        UPDATE tasks
        SET status = 'propusteno'
        WHERE status = 'zakazano'
        AND datum IS NOT NULL
        AND vreme IS NOT NULL
        AND DATE_ADD(
            DATE_ADD(CONCAT(datum, ' ', vreme), INTERVAL trajanje MINUTE),
            INTERVAL 24 HOUR
        ) < '$now'
    ";

    $conn->query($sql);
}

function isTaskInProgress($row) {
    if ($row['status'] != "zakazano") {
        return false;
    }

    if (empty($row['datum']) || empty($row['vreme'])) {
        return false;
    }

    $trajanje = (int)$row['trajanje'];

    if ($trajanje <= 0) {
        return false;
    }

    $start = strtotime($row['datum'] . " " . $row['vreme']);
    $end = $start + ($trajanje * 60);
    $now = time();

    return ($now >= $start && $now <= $end);
}

function renderActions($row) {
    $dugme = "";

    if ($row['status'] != "zavrseno") {
        $dugme .= "<br><br><a href='zavrsi.php?id={$row['id']}'>✔ Završi</a>";
    }

    if ($row['status'] != "zavrseno" && $row['status'] != "todo") {
        $dugme .= " | <a href='otkazi.php?id={$row['id']}'>✖ Otkaži</a>";
    }

    if ($row['status'] != "obrisano") {
        $dugme .= " | <a href='obrisi.php?id={$row['id']}' onclick=\"return confirm('Premestiti obavezu u korpu?')\"> Obriši</a>";
    }

    return $dugme;
}

function deleteTask($conn, $id) {
    $id = (int)$id;

    if ($id <= 0) {
        return false;
    }

    $sql = "
        UPDATE tasks
        SET status = 'obrisano'
        WHERE id = $id
    ";

    return $conn->query($sql);
}