<?php
include "config.php";

$program_id = (int)($_GET['program_id'] ?? $_POST['program_id'] ?? 0);

if ($program_id <= 0) {
    die("Program održavanja nije prosleđen.");
}

/* =========================
   UČITAJ STAVKU PROGRAMA
========================= */
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

/* =========================
   DODAJ PONUDU PROGRAMU
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_ponudu'])) {
    $ponuda_id = (int)($_POST['ponuda_id'] ?? 0);

    if ($ponuda_id > 0 && $kategorija !== '') {

        /*
           Zaštita:
           - ponuda mora biti aktivna
           - ponuda mora biti iz iste kategorije kao stavka programa
           - ponuda mora biti za istu zgradu ili opšta ponuda bez sz_id
        */
        $dozvoljena = $conn->query("
            SELECT id
            FROM ponude
            WHERE id = $ponuda_id
              AND aktivna = 1
              AND kategorija = '$kategorijaSql'
              AND (sz_id = $sz_id OR sz_id IS NULL OR sz_id = 0)
            LIMIT 1
        ");

        if (!$dozvoljena || $dozvoljena->num_rows == 0) {
            die("Greška: izabrana ponuda ne odgovara kategoriji ove stavke programa održavanja.");
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

/* =========================
   OZNAČI IZABRANU PONUDU
========================= */
if (isset($_GET['izaberi'])) {
    $veza_id = (int)$_GET['izaberi'];

    $conn->query("
        UPDATE program_odrzavanje_ponude
        SET izabrana = 0
        WHERE program_id = $program_id
    ");

    $conn->query("
        UPDATE program_odrzavanje_ponude
        SET izabrana = 1
        WHERE id = $veza_id
          AND program_id = $program_id
    ");

    header("Location: program_ponude.php?program_id=$program_id");
    exit;
}

/* =========================
   UKLONI PONUDU SA PROGRAMA
========================= */
if (isset($_GET['ukloni'])) {
    $veza_id = (int)$_GET['ukloni'];

    $conn->query("
        DELETE FROM program_odrzavanje_ponude
        WHERE id = $veza_id
          AND program_id = $program_id
    ");

    header("Location: program_ponude.php?program_id=$program_id");
    exit;
}

/* =========================
   POVEZANE PONUDE
========================= */
$povezanePonude = $conn->query("
    SELECT pop.id AS veza_id,
           pop.izabrana,
           p.id AS ponuda_id,
           p.naziv,
           p.dobavljac,
           p.datum_ponude,
           p.vazi_do,
           p.iznos,
           p.opis,
           p.kategorija
    FROM program_odrzavanje_ponude pop
    JOIN ponude p ON p.id = pop.ponuda_id
    WHERE pop.program_id = $program_id
    ORDER BY pop.izabrana DESC, p.iznos ASC, p.datum_ponude DESC
");

/* =========================
   AKTIVNE PONUDE ISTE KATEGORIJE
========================= */
$svePonude = false;

if ($kategorija !== '') {
    $svePonude = $conn->query("
        SELECT p.*
        FROM ponude p
        WHERE p.aktivna = 1
          AND p.kategorija = '$kategorijaSql'
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

<div class="container">

    <h1>Ponude za aktivnost</h1>

    <p>
        <strong><?= htmlspecialchars($program['sz_naziv']) ?></strong><br>
        <?= htmlspecialchars($program['sz_adresa']) ?>
    </p>

    <h2><?= htmlspecialchars($program['aktivnost']) ?></h2>

    <p>
        <strong>Kategorija:</strong>
        <?= $kategorija !== '' ? htmlspecialchars($kategorija) : '<span style="color:red;">Nije upisana</span>' ?><br>

        <strong>Procena troška:</strong>
        <?= number_format((float)$program['procenjeni_trosak'], 2, ',', '.') ?> RSD<br>

        <strong>Status:</strong>
        <?= $program['zavrsena'] ? 'Završeno' : 'Planirano' ?>
    </p>

    <?php if (!empty($program['napomena'])): ?>
        <p><strong>Napomena:</strong><br><?= nl2br(htmlspecialchars($program['napomena'])) ?></p>
    <?php endif; ?>

    <h2>Povezane ponude</h2>

    <table>
        <tr>
            <th>Ponuda</th>
            <th>Kategorija</th>
            <th>Dobavljač</th>
            <th>Datum</th>
            <th>Važi do</th>
            <th>Iznos</th>
            <th>Status</th>
            <th>Akcija</th>
        </tr>

        <?php if ($povezanePonude && $povezanePonude->num_rows > 0): ?>
            <?php while ($ponuda = $povezanePonude->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($ponuda['naziv']) ?></strong>
                        <?php if (!empty($ponuda['opis'])): ?>
                            <br><small><?= nl2br(htmlspecialchars($ponuda['opis'])) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($ponuda['kategorija']) ?></td>
                    <td><?= htmlspecialchars($ponuda['dobavljac']) ?></td>
                    <td><?= htmlspecialchars($ponuda['datum_ponude']) ?></td>
                    <td><?= htmlspecialchars($ponuda['vazi_do']) ?></td>
                    <td>
                        <?= $ponuda['iznos'] !== null
                            ? number_format((float)$ponuda['iznos'], 2, ',', '.') . ' RSD'
                            : '-' ?>
                    </td>
                    <td><?= $ponuda['izabrana'] ? '<strong>Izabrana</strong>' : 'Povezana' ?></td>
                    <td>
                        <?php if (!$ponuda['izabrana']): ?>
                            <a class="btn" href="program_ponude.php?program_id=<?= $program_id ?>&izaberi=<?= $ponuda['veza_id'] ?>">
                                Izaberi
                            </a>
                        <?php endif; ?>

                        <a class="btn" href="program_ponude.php?program_id=<?= $program_id ?>&ukloni=<?= $ponuda['veza_id'] ?>"
                           onclick="return confirm('Ukloniti ovu ponudu iz aktivnosti?')">
                            Ukloni
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="8">Nema povezanih ponuda.</td>
            </tr>
        <?php endif; ?>
    </table>

    <h2>Dodaj postojeću ponudu</h2>

    <?php if ($kategorija === ''): ?>

        <p style="color:red;">
            Ova stavka programa nema upisanu kategoriju, pa nije moguće povezati ponudu.
            Prvo upiši kategoriju u tabeli <strong>program_odrzavanja</strong>.
        </p>

    <?php else: ?>

        <p>
            <small>
                Prikazuju se samo aktivne ponude iz kategorije
                <strong><?= htmlspecialchars($kategorija) ?></strong>.
            </small>
        </p>

        <form method="post">
            <input type="hidden" name="program_id" value="<?= $program_id ?>">

            <select name="ponuda_id" required>
                <option value="">-- Izaberi ponudu --</option>

                <?php if ($svePonude && $svePonude->num_rows > 0): ?>
                    <?php while ($p = $svePonude->fetch_assoc()): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= htmlspecialchars($p['naziv']) ?>
                            <?= !empty($p['dobavljac']) ? ' - ' . htmlspecialchars($p['dobavljac']) : '' ?>
                            <?= $p['iznos'] !== null ? ' - ' . number_format((float)$p['iznos'], 2, ',', '.') . ' RSD' : '' ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>

            <button type="submit" name="dodaj_ponudu" class="btn">Dodaj ponudu</button>
        </form>

    <?php endif; ?>

    <br>
    <a href="program_odrzavanja.php?sz_id=<?= $sz_id ?>">← Nazad na program održavanja</a>

</div>

</body>
</html>
