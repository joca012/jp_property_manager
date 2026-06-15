<?php

function getStatusColor($status) {

    switch ($status) {

        case "zakazano":
            return "#ffc107"; // žuto

        case "zavrseno":
            return "#28a745"; // zeleno

        case "propusteno":
            return "#dc3545"; // crveno

        case "todo":
            return "#dc3545"; // crveno

        default:
            return "#6c757d";
    }
}
function autoUpdateStatus($conn) {

    $now = date('Y-m-d H:i:s');

    $sql = "UPDATE tasks 
            SET status = 'propusteno'
            WHERE status = 'zakazano'
            AND CONCAT(datum, ' ', vreme) < '$now'
            AND status != 'zavrseno'";

    $conn->query($sql);
}