<?php
include "config.php";

$sz_id = (int)($_GET['sz_id'] ?? 0);

if (!$sz_id) {
    die("Nije prosleđen ID stambene zajednice.");
}

/* Brišemo stari program */
$conn->query("
    DELETE FROM program_odrzavanja
    WHERE sz_id = $sz_id
");

/* Uzimamo opremu zgrade */
$oprema = $conn->query("
    SELECT *
    FROM oprema_zgrade
    WHERE sz_id = $sz_id
    AND aktivna = 1
");

while ($o = $oprema->fetch_assoc()) {

    $tip = $conn->real_escape_string($o['tip']);

    $aktivnosti = $conn->query("
        SELECT *
        FROM sifarnik_odrzavanja
        WHERE tip_opreme = '$tip'
        AND aktivna = 1
    ");

    while ($a = $aktivnosti->fetch_assoc()) {

        $stmt = $conn->prepare("
            INSERT INTO program_odrzavanja
            (
                sz_id,
                aktivnost,
                mesec,
                procenjeni_trosak,
                obavezna
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $mesec = 1;

        $stmt->bind_param(
            "isidi",
            $sz_id,
            $a['aktivnost'],
            $mesec,
            $a['okvirna_cena'],
            $a['obavezna']
        );

        $stmt->execute();
    }
}

header("Location: program_odrzavanja.php?sz_id=".$sz_id);
exit;