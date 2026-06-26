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

    /*
       Obaveza koja nije potvrđena kao završena 24h nakon isteka
       prelazi u status propusteno i skida joj se datum/vreme.
       Tako se vraća u TODO tok i mora ponovo da se zakaže.
    */
    $sql = "
        UPDATE tasks
        SET status = 'propusteno',
            datum = NULL,
            vreme = NULL
        WHERE status = 'zakazano'
        AND datum IS NOT NULL
        AND vreme IS NOT NULL
        AND DATE_ADD(
            DATE_ADD(CONCAT(datum, ' ', vreme), INTERVAL trajanje MINUTE),
            INTERVAL 24 HOUR
        ) < '$now'
    ";

    $conn->query($sql);

    /*
       Čišćenje ranije propuštenih obaveza koje su ostale sa starim terminom
       iz prethodne verzije logike.
    */
    $conn->query("
        UPDATE tasks
        SET datum = NULL,
            vreme = NULL
        WHERE status = 'propusteno'
        AND (datum IS NOT NULL OR vreme IS NOT NULL)
    ");
}

function isTaskInProgress($row) {
    if (($row['status'] ?? '') == "propusteno") {
        return true;
    }

    if (($row['status'] ?? '') != "zakazano") {
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

function currentReturnUrl() {
    $uri = $_SERVER['REQUEST_URI'] ?? 'index.php';

    $path = parse_url($uri, PHP_URL_PATH) ?: 'index.php';
    $query = parse_url($uri, PHP_URL_QUERY);

    $file = basename($path);

    if ($file == '' || $file == '/') {
        $file = 'index.php';
    }

    return $file . ($query ? '?' . $query : '');
}

function renderActions($row, $returnUrl = null) {
    $id = (int)$row['id'];
    $status = $row['status'] ?? '';
    $return = urlencode($returnUrl ?? currentReturnUrl());

    $dugme = "";

    if ($status == "zakazano") {
        $dugme .= "<br><br><a href='zavrsi.php?id=$id&return=$return'>✔ Završi</a>";
        $dugme .= " | <a href='otkazi.php?id=$id&return=$return'>✖ Otkaži</a>";
        $dugme .= " | <a href='izmeni.php?id=$id&return=$return'>✏ Izmeni</a>";
    }

    if ($status == "zavrseno") {
        $dugme .= "<br><br><a href='vrati.php?id=$id&return=$return'>↩ Vrati</a>";
        $dugme .= " | <a href='izmeni.php?id=$id&return=$return'>✏ Izmeni</a>";
    }

    if ($status == "todo" || $status == "propusteno") {
        $dugme .= "<br><br><a href='izmeni.php?id=$id&return=$return'>✏ Izmeni</a>";
    }

    if ($status != "obrisano") {
        $dugme .= " | <a href='obrisi.php?id=$id' onclick=\"return confirm('Premestiti obavezu u korpu?')\">🗑 Obriši</a>";
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

function srpskiDan($datum) {
    $dani = [
        'Monday' => 'Ponedeljak',
        'Tuesday' => 'Utorak',
        'Wednesday' => 'Sreda',
        'Thursday' => 'Četvrtak',
        'Friday' => 'Petak',
        'Saturday' => 'Subota',
        'Sunday' => 'Nedelja'
    ];

    return $dani[date('l', strtotime($datum))];
}
