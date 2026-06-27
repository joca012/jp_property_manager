<?php
include "config.php";

$program_id = (int)($_GET['program_id'] ?? $_POST['program_id'] ?? 0);

$programResult = $conn->query("
    SELECT po.*, sz.naziv AS sz_naziv, sz.adresa AS sz_adresa
    FROM program_odrzavanja po
    JOIN stambene_zajednice sz ON sz.id = po.sz_id
    WHERE po.id = $program_id
");

if (!$programResult || $programResult->num_rows == 0) {
    die("Stavka programa održavanja nije pronađena.");
}

$program = $programResult->fetch_assoc();
$sz_id = (int)$program['sz_id'];
$kategorija = trim($program['kategorija'] ?? '');
$kategorijaSql = $conn->real_escape_string($kategorija);

/* DODAJ PONUDU PROGRAMU */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_ponudu'])) {
    $ponuda_id = (int)($_POST['ponuda_id'] ?? 0);

    if ($ponuda_id > 0 && $kategorija !== '') {
        /*
            Nova provera:
            - ponuda mora biti aktivna;
            - ponuda mora biti vezana za izvođača;
            - taj izvođač mora imati kategoriju koja odgovara stavki programa;
            - ponuda mora biti za istu zgradu ili opšta ponuda bez sz_id.
        */
        $dozvoljena = $conn->query("
            SELECT p.id
            FROM ponude p
            JOIN izvodjac_kategorije ik ON ik.izvodjac_id = p.izvodjac_id
            JOIN kategorije_radova kr ON kr.id = ik.kategorija_id
            WHERE p.id = $ponuda_id
              AND p.aktivna = 1
              AND kr.naziv = '$kategorijaSql'
              AND (p.sz_id = $sz_id OR p.sz_id IS NULL OR p.sz_id = 0)
            LIMIT 1
        ");

        if (!$dozvoljena || $dozvoljena->num_rows == 0) {
            die("Greška: izabrana ponuda nije od izvođača koji obavlja ovu kategoriju radova.");
        }

        $postoji = $conn->query("
            SELECT id
            FROM program_odrzavanje_ponude
            WHERE program_id = $program_id
              AND ponuda_id = $ponuda_id
            LIMIT 1
        ");

        if ($postoji && $postoji->num_rows == 0) {
            $conn->query("
                INSERT INTO program_odrzavanje_ponude (program_id, ponuda_id, izabrana)
                VALUES ($program_id, $ponuda_id, 0)
            ");
        }
    }

    header("Location: program_ponude.php?program_id=$program_id");
    exit;
}

/* OZNAČI IZABRANU PONUDU */
if (isset($_GET['izaberi'])) {
    $veza_id = (int)$_GET['izaberi'];
    $conn->query("UPDATE program_odrzavanje_ponude SET izabrana = 0 WHERE program_id = $program_id");
    $conn->query("UPDATE program_odrzavanje_ponude SET izabrana = 1 WHERE id = $veza_id AND program_id = $program_id");
    header("Location: program_ponude.php?program_id=$program_id");
    exit;
}

/* UKLONI PONUDU SA PROGRAMA */
if (isset($_GET['ukloni'])) {
    $veza_id = (int)$_GET['ukloni'];
    $conn->query("DELETE FROM program_odrzavanje_ponude WHERE id = $veza_id AND program_id = $program_id");
    header("Location: program_ponude.php?program_id=$program_id");
    exit;
}

$povezanePonude = $conn->query("
    SELECT pop.id AS veza_id,
           pop.izabrana,
           p.id AS ponuda_id,
           p.naziv,
           COALESCE(i.naziv, p.dobavljac) AS izvodjac,
           p.datum_ponude,
           p.vazi_do,
           p.iznos,
           p.opis,
           GROUP_CONCAT(DISTINCT kr.naziv ORDER BY kr.naziv SEPARATOR ', ') AS kategorije_izvodjaca
    FROM program_odrzavanje_ponude pop
    JOIN ponude p ON p.id = pop.ponuda_id
    LEFT JOIN izvodjaci i ON i.id = p.izvodjac_id
    LEFT JOIN izvodjac_kategorije ik ON ik.izvodjac_id = i.id
    LEFT JOIN kategorije_radova kr ON kr.id = ik.kategorija_id
    WHERE pop.program_id = $program_id
    GROUP BY pop.id, p.id
    ORDER BY pop.izabrana DESC, p.iznos ASC, p.datum_ponude DESC
");

$svePonude = false;
if ($kategorija !== '') {
    $svePonude = $conn->query("
        SELECT p.*, COALESCE(i.naziv, p.dobavljac) AS izvodjac
        FROM ponude p
        JOIN izvodjaci i ON i.id = p.izvodjac_id
        JOIN izvodjac_kategorije ik ON ik.izvodjac_id = i.id
        JOIN kategorije_radova kr ON kr.id = ik.kategorija_id
        WHERE p.aktivna = 1
          AND kr.naziv = '$kategorijaSql'
          AND (p.sz_id = $sz_id OR p.sz_id IS NULL OR p.sz_id = 0)
          AND NOT EXISTS (
              SELECT 1
              FROM program_odrzavanje_ponude pop
              WHERE pop.program_id = $program_id
                AND pop.ponuda_id = p.id
          )
        ORDER BY p.datum_ponude DESC, p.naziv
    ");
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Ponude - <?= htmlspecialchars($program['aktivnost']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include "header.php"; ?>

<div class="container">
    <h1>Ponude za aktivnost</h1>

    <h2><?= htmlspecialchars($program['aktivnost']) ?></h2>
    <p><strong>Kategorija:</strong> <?= $kategorija !== '' ? htmlspecialchars($kategorija) : 'Nije upisana' ?></p>
    <p><strong>Procena troška:</strong> <?= number_format((float)($program['procena_troska'] ?? 0), 2, ',', '.') ?> RSD</p>
    <p><strong>Status:</strong> <?= htmlspecialchars($program['status'] ?? '') ?></p>

    <?php if (!empty($program['napomena'])): ?>
        <p><strong>Napomena:</strong> <?= nl2br(htmlspecialchars($program['napomena'])) ?></p>
    <?php endif; ?>

    <h2>Povezane ponude</h2>
    <div class="table-wrap">
    <table>
        <tr>
            <th>Ponuda</th>
            <th>Izvođač</th>
            <th>Kategorije izvođača</th>
            <th>Datum</th>
            <th>Važi do</th>
            <th>Iznos</th>
            <th>Status</th>
            <th>Akcija</th>
        </tr>
        <?php if ($povezanePonude && $povezanePonude->num_rows > 0): ?>
            <?php while($p = $povezanePonude->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($p['naziv']) ?></td>
                    <td><?= htmlspecialchars($p['izvodjac'] ?? '') ?></td>
                    <td><?= htmlspecialchars($p['kategorije_izvodjaca'] ?? '') ?></td>
                    <td><?= htmlspecialchars($p['datum_ponude'] ?? '') ?></td>
                    <td><?= htmlspecialchars($p['vazi_do'] ?? '') ?></td>
                    <td><?= number_format((float)($p['iznos'] ?? 0), 2, ',', '.') ?> RSD</td>
                    <td><?= ((int)$p['izabrana'] === 1) ? 'Izabrana' : 'Povezana' ?></td>
                    <td>
                        <div class="action-cell">
                            <?php if ((int)$p['izabrana'] !== 1): ?>
                                <a class="btn btn-small" href="program_ponude.php?program_id=<?= $program_id ?>&izaberi=<?= (int)$p['veza_id'] ?>">✅ Izaberi</a>
                            <?php endif; ?>
                            <a class="btn btn-small btn-danger" href="program_ponude.php?program_id=<?= $program_id ?>&ukloni=<?= (int)$p['veza_id'] ?>" onclick="return confirm('Ukloniti ponudu sa ove stavke programa?')">🗑️ Ukloni</a>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="8">Nema povezanih ponuda.</td></tr>
        <?php endif; ?>
    </table>
    </div>

    <h2>Dodaj postojeću ponudu</h2>
    <?php if ($kategorija === ''): ?>
        <p>Ova stavka programa nema upisanu kategoriju, pa nije moguće povezati ponudu.</p>
    <?php else: ?>
        <p>Prikazuju se samo aktivne ponude izvođača koji imaju kategoriju: <strong><?= htmlspecialchars($kategorija) ?></strong>.</p>
        <form method="POST">
            <input type="hidden" name="program_id" value="<?= $program_id ?>">
            <input type="hidden" name="dodaj_ponudu" value="1">

            <select name="ponuda_id" required>
                <option value="">-- Izaberi ponudu --</option>
                <?php if ($svePonude && $svePonude->num_rows > 0): ?>
                    <?php while($p = $svePonude->fetch_assoc()): ?>
                        <option value="<?= (int)$p['id'] ?>">
                            <?= htmlspecialchars($p['naziv']) ?> — <?= htmlspecialchars($p['izvodjac'] ?? '') ?>
                            <?php if (!empty($p['datum_ponude'])): ?>
                                (<?= htmlspecialchars($p['datum_ponude']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>

            <button class="btn" type="submit">➕ Dodaj ponudu</button>
        </form>
    <?php endif; ?>

    <p><a class="btn btn-secondary" href="program_odrzavanja.php?sz_id=<?= $sz_id ?>">⬅ Nazad na program održavanja</a></p>
</div>
</body>
</html>
