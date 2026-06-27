<?php
include "config.php";

$sz_id = (int)($_GET['sz_id'] ?? 0);

if (!$sz_id) {
    die("Nije prosleđen ID stambene zajednice.");
}

function jp_columns(mysqli $conn, string $table): array {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM `$table`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[$row['Field']] = true;
        }
    }
    return $cols;
}

function jp_val(mysqli $conn, $value, bool $numeric = false): string {
    if ($value === null || $value === '') {
        return "NULL";
    }
    if ($numeric) {
        return (string)(float)$value;
    }
    return "'" . $conn->real_escape_string((string)$value) . "'";
}

function jp_insert_program(mysqli $conn, int $sz_id, array $a, array $programCols): void {
    $cols = ['sz_id'];
    $vals = [(string)$sz_id];

    $map = [
        'aktivnost' => ['aktivnost', false],
        'kategorija' => ['kategorija', false],
        'mesec' => ['mesec', true],
        'procenjeni_trosak' => ['okvirna_cena', true],
        'obavezna' => ['obavezna', true],
        'ucestalost_tip' => ['ucestalost_tip', false],
        'ucestalost_broj' => ['ucestalost_broj', true],
        'datum_prve_kontrole' => ['datum_prve_kontrole', false],
        'datum_sledece_kontrole' => ['datum_sledece_kontrole', false],
        'nacin_obracuna' => ['nacin_obracuna', false],
        'napomena' => ['napomena', false],
    ];

    foreach ($map as $programCol => [$sourceCol, $numeric]) {
        if (!isset($programCols[$programCol])) {
            continue;
        }

        $value = $a[$sourceCol] ?? null;

        if ($programCol === 'mesec' && ($value === null || $value === '')) {
            $value = 1;
        }

        $cols[] = $programCol;
        $vals[] = jp_val($conn, $value, $numeric);
    }

    $sql = "INSERT INTO program_odrzavanja (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ")";
    $conn->query($sql);
}

$programCols = jp_columns($conn, 'program_odrzavanja');

/* Brišemo postojeći program za ovu zgradu i pravimo nov godišnji program. */
$conn->query("DELETE FROM program_odrzavanje_ponude WHERE program_id IN (SELECT id FROM program_odrzavanja WHERE sz_id = $sz_id)");
$conn->query("DELETE FROM program_odrzavanja WHERE sz_id = $sz_id");

/* Aktivna oprema zgrade određuje koje neobavezne/opremne stavke ulaze u program. */
$tipovi = [];
$oprema = $conn->query("SELECT tip FROM oprema_zgrade WHERE sz_id = $sz_id AND aktivna = 1");
if ($oprema) {
    while ($o = $oprema->fetch_assoc()) {
        $tipovi[] = "'" . $conn->real_escape_string($o['tip']) . "'";
    }
}

$where = "aktivna = 1 AND (obavezna = 1";
if (!empty($tipovi)) {
    $where .= " OR tip_opreme IN (" . implode(',', $tipovi) . ")";
}
$where .= ")";

$aktivnosti = $conn->query("SELECT * FROM sifarnik_odrzavanja WHERE $where ORDER BY obavezna DESC, tip_opreme, aktivnost");
if ($aktivnosti) {
    while ($a = $aktivnosti->fetch_assoc()) {
        jp_insert_program($conn, $sz_id, $a, $programCols);
    }
}

header("Location: program_odrzavanja.php?sz_id=" . $sz_id);
exit;
