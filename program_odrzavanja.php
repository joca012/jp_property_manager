<?php
include "config.php";

$sz_id = (int)($_GET['sz_id'] ?? 0);

$result = $conn->query("SELECT * FROM stambene_zajednice WHERE id = $sz_id");

if (!$result || $result->num_rows == 0) {
    die("Stambena zajednica nije pronađena.");
}

$sz = $result->fetch_assoc();

/*
    Novi prikaz:
    - ne grupiše odmah po mesecima
    - prikazuje stavke programa održavanja
    - predviđa učestalost i sledeću kontrolu

    Potrebne kolone u program_odrzavanja:
    kategorija
    ucestalost_tip
    ucestalost_broj
    datum_prve_kontrole
    datum_sledece_kontrole
    nacin_obracuna
*/

$program = $conn->query("
    SELECT *
    FROM program_odrzavanja
    WHERE sz_id = $sz_id
    ORDER BY aktivnost
");

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
               onclick="return confirm('Generisati novi program? Postojeći program za ovu zgradu biće obrisan.')">
               Generiši program
            </a>
        </div>
    </div>

    <h2>Stavke održavanja</h2>

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

                    <td><?= number_format((float)$row['procenjeni_trosak'], 2, ',', '.') ?> RSD</td>

                    <td><?= prikaziObracun($row['nacin_obracuna'] ?? null) ?></td>

                    <td><?= $row['obavezna'] ? 'Da' : 'Ne' ?></td>

                    <td><?= $row['zavrsena'] ? 'Završeno' : 'Planirano' ?></td>

                    <td>
                        <a class="btn" href="program_ponude.php?program_id=<?= $row['id'] ?>">
                            Ponude
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="10">Nema unetih stavki programa održavanja.</td>
            </tr>
        <?php endif; ?>
    </table>

    <br>

    <a href="zajednica.php?id=<?= $sz_id ?>">← Nazad na zajednicu</a>

</div>

</body>
</html>
