<?php
include "config.php";

$sz_id = (int)($_GET['sz_id'] ?? $_POST['sz_id'] ?? 0);

$result = $conn->query("SELECT * FROM stambene_zajednice WHERE id = $sz_id");
if (!$result || $result->num_rows == 0) {
    die("Stambena zajednica nije pronađena.");
}
$sz = $result->fetch_assoc();

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

function prikaziUcestalost($row) {
    $tip = $row['ucestalost_tip'] ?? '';
    $broj = (int)($row['ucestalost_broj'] ?? 0);

    if ($tip === '' || $broj <= 0) {
        return '-';
    }
    if ($tip === 'meseci') {
        return "na $broj meseci";
    }
    if ($tip === 'godina') {
        return $broj == 1 ? "godišnje" : "na $broj godine";
    }
    if ($tip === 'nedeljno') {
        return "$broj x nedeljno";
    }
    if ($tip === 'mesecno') {
        return "$broj x mesečno";
    }
    return htmlspecialchars($tip . ' ' . $broj);
}

function prikaziDatum($datum) {
    if (!$datum || $datum === '0000-00-00') {
        return '-';
    }
    return date('d.m.Y', strtotime($datum));
}

function prikaziObracun($vrednost) {
    if (!$vrednost) {
        return '-';
    }
    $mapa = [
        'po_kontroli' => 'po kontroli',
        'mesecno' => 'mesečno',
        'godisnje' => 'godišnje',
        'po_intervenciji' => 'po intervenciji'
    ];
    return $mapa[$vrednost] ?? htmlspecialchars($vrednost);
}

$programCols = jp_columns($conn, 'program_odrzavanja');

/* Uklanjanje stavke iz godišnjeg programa. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ukloni_stavku'])) {
    $program_id = (int)($_POST['program_id'] ?? 0);

    if ($program_id > 0) {
        $conn->query("DELETE FROM program_odrzavanje_ponude WHERE program_id = $program_id");
        $conn->query("DELETE FROM program_odrzavanja WHERE id = $program_id AND sz_id = $sz_id");
    }

    header("Location: program_odrzavanja.php?sz_id=" . $sz_id);
    exit;
}

/* Ručno dodavanje stavke iz šifarnika održavanja. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_stavku'])) {
    $sifarnik_id = (int)($_POST['sifarnik_id'] ?? 0);

    if ($sifarnik_id > 0) {
        $res = $conn->query("SELECT * FROM sifarnik_odrzavanja WHERE id = $sifarnik_id AND aktivna = 1 LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $a = $res->fetch_assoc();
            jp_insert_program($conn, $sz_id, $a, $programCols);
        }
    }

    header("Location: program_odrzavanja.php?sz_id=" . $sz_id);
    exit;
}

$program = $conn->query("SELECT * FROM program_odrzavanja WHERE sz_id = $sz_id ORDER BY aktivnost");

/* Za padajuću listu nudimo aktivne stavke koje još nisu u programu. */
$dostupneStavke = $conn->query("
    SELECT so.*
    FROM sifarnik_odrzavanja so
    WHERE so.aktivna = 1
    AND NOT EXISTS (
        SELECT 1
        FROM program_odrzavanja po
        WHERE po.sz_id = $sz_id
        AND po.aktivnost = so.aktivnost
    )
    ORDER BY so.obavezna DESC, so.tip_opreme, so.aktivnost
");
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Program održavanja - <?= htmlspecialchars($sz['naziv']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include "header.php"; ?>

<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:20px;">
        <div>
            <h1>Program održavanja</h1>
            <p>
                <strong><?= htmlspecialchars($sz['naziv']) ?></strong><br>
                <?= htmlspecialchars($sz['adresa']) ?>
            </p>
        </div>
        <div>
            <a class="btn" href="generisi_program.php?sz_id=<?= $sz_id ?>"
               onclick="return confirm('Generisati novi godišnji program? Postojeći program za ovu zgradu biće obrisan, a zatim ponovo formiran iz obaveznih stavki i trenutno aktivne opreme zgrade.')">
                Generiši program
            </a>
        </div>
    </div>

    <h2>Dodaj stavku u program</h2>
    <form method="post" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
        <input type="hidden" name="sz_id" value="<?= $sz_id ?>">
        <div>
            <label>Stavka</label><br>
            <select name="sifarnik_id" required>
                <option value="">-- Izaberi stavku --</option>
                <?php if ($dostupneStavke && $dostupneStavke->num_rows > 0): ?>
                    <?php while ($s = $dostupneStavke->fetch_assoc()): ?>
                        <option value="<?= (int)$s['id'] ?>">
                            <?= htmlspecialchars($s['aktivnost']) ?>
                            <?= !empty($s['tip_opreme']) ? ' / ' . htmlspecialchars($s['tip_opreme']) : '' ?>
                            <?= !empty($s['obavezna']) ? ' / obavezno' : '' ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>
        <button class="btn" type="submit" name="dodaj_stavku">➕ Dodaj</button>
    </form>

    <h2>Stavke održavanja</h2>
    <div class="table-wrap">
    <table>
        <tr>
            <th>Aktivnost</th>
            <th>Kategorija</th>
            <th>Učestalost</th>
            <th>Prva kontrola</th>
            <th>Sledeća kontrola</th>
            <th>Trošak</th>
            <th>Obračun</th>
            <th>Obavezno</th>
            <th>Status</th>
            <th>Ponude</th>
            <th>Akcija</th>
        </tr>

        <?php if ($program && $program->num_rows > 0): ?>
            <?php while ($row = $program->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($row['aktivnost']) ?></strong>
                        <?php if (!empty($row['napomena'])): ?>
                            <br><small><?= nl2br(htmlspecialchars($row['napomena'])) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['kategorija'] ?? '') ?></td>
                    <td><?= prikaziUcestalost($row) ?></td>
                    <td><?= prikaziDatum($row['datum_prve_kontrole'] ?? null) ?></td>
                    <td><?= prikaziDatum($row['datum_sledece_kontrole'] ?? null) ?></td>
                    <td><?= number_format((float)($row['procenjeni_trosak'] ?? 0), 2, ',', '.') ?> RSD</td>
                    <td><?= prikaziObracun($row['nacin_obracuna'] ?? null) ?></td>
                    <td><?= !empty($row['obavezna']) ? 'Da' : 'Ne' ?></td>
                    <td><?= !empty($row['zavrsena']) ? 'Završeno' : 'Planirano' ?></td>
                    <td>
                        <a class="btn btn-small" href="program_ponude.php?program_id=<?= (int)$row['id'] ?>">📄 Ponude</a>
                    </td>
                    <td>
                        <div class="action-cell">
                            <form method="post" style="display:inline;" onsubmit="return confirm('Ukloniti ovu stavku iz godišnjeg programa?')">
                                <input type="hidden" name="sz_id" value="<?= $sz_id ?>">
                                <input type="hidden" name="program_id" value="<?= (int)$row['id'] ?>">
                                <button class="btn btn-small btn-danger" type="submit" name="ukloni_stavku">🗑️ Ukloni</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="11">Nema unetih stavki programa održavanja.</td>
            </tr>
        <?php endif; ?>
    </table>
    </div>

    <br>
    <a class="btn btn-secondary" href="zajednica.php?id=<?= $sz_id ?>">⬅ Nazad na zajednicu</a>
</div>
</body>
</html>
