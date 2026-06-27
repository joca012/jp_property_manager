<?php
include "config.php";

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$sz_id = (int)($_GET['sz_id'] ?? $_POST['sz_id'] ?? 0);
$ctx = $sz_id > 0 ? '?sz_id=' . $sz_id : '';


function kategorijeIzvodjacaTekst(mysqli $conn, int $izvodjac_id): string
{
    $res = $conn->query("
        SELECT GROUP_CONCAT(kr.naziv ORDER BY kr.naziv SEPARATOR ', ') AS kategorije
        FROM izvodjac_kategorije ik
        JOIN kategorije_radova kr ON kr.id = ik.kategorija_id
        WHERE ik.izvodjac_id = $izvodjac_id
    ");
    if ($res && $row = $res->fetch_assoc()) {
        return $row['kategorije'] ?? '';
    }
    return '';
}

$result = $conn->query("SELECT * FROM ponude WHERE id = $id AND aktivna = 1");
if (!$result || $result->num_rows == 0) {
    die("Ponuda nije pronađena.");
}
$ponuda = $result->fetch_assoc();

$izvodjaci = $conn->query("
    SELECT i.id, i.naziv,
           GROUP_CONCAT(kr.naziv ORDER BY kr.naziv SEPARATOR ', ') AS kategorije
    FROM izvodjaci i
    LEFT JOIN izvodjac_kategorije ik ON ik.izvodjac_id = i.id
    LEFT JOIN kategorije_radova kr ON kr.id = ik.kategorija_id
    WHERE i.aktivan = 1
    GROUP BY i.id
    ORDER BY i.naziv
");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $naziv = trim($_POST['naziv'] ?? '');
    $izvodjac_id = (int)($_POST['izvodjac_id'] ?? 0);
    $datum_ponude = $_POST['datum_ponude'] ?: null;
    $vazi_do = $_POST['vazi_do'] ?: null;

    if ($naziv === '') {
        die("Naziv ponude je obavezan.");
    }
    if ($izvodjac_id <= 0) {
        die("Izvođač je obavezan.");
    }

    $resIzvodjac = $conn->query("SELECT naziv FROM izvodjaci WHERE id = $izvodjac_id AND aktivan = 1");
    if (!$resIzvodjac || $resIzvodjac->num_rows == 0) {
        die("Izabrani izvođač nije pronađen.");
    }
    $izvodjacRow = $resIzvodjac->fetch_assoc();
    $izvodjac_naziv = $izvodjacRow['naziv'];
    $kategorija_tekst = kategorijeIzvodjacaTekst($conn, $izvodjac_id);

    $stmt = $conn->prepare("
        UPDATE ponude
        SET naziv = ?, izvodjac_id = ?, dobavljac = ?, kategorija = ?, datum_ponude = ?, vazi_do = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sissssi", $naziv, $izvodjac_id, $izvodjac_naziv, $kategorija_tekst, $datum_ponude, $vazi_do, $id);
    $stmt->execute();

    header("Location: ponude.php" . $ctx);
    exit;
}
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Izmeni ponudu</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include "header.php"; ?>

<div class="container">
    <h1>Izmeni ponudu</h1>

    <?php if ($sz_id > 0): ?>
        <p><a href="ponude.php?sz_id=<?= $sz_id ?>">← Nazad na ponude</a></p>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="sz_id" value="<?= $sz_id ?>">

        <label>Naziv ponude</label>
        <input type="text" name="naziv" value="<?= htmlspecialchars($ponuda['naziv'] ?? '') ?>" required>

        <label>Izvođač</label>
        <select name="izvodjac_id" required>
            <option value="">-- Izaberi izvođača --</option>
            <?php if ($izvodjaci): ?>
                <?php while($i = $izvodjaci->fetch_assoc()): ?>
                    <option value="<?= (int)$i['id'] ?>" <?= ((int)($ponuda['izvodjac_id'] ?? 0) === (int)$i['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($i['naziv']) ?><?= !empty($i['kategorije']) ? ' — ' . htmlspecialchars($i['kategorije']) : '' ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>

        <label>Datum ponude</label>
        <input type="date" name="datum_ponude" value="<?= htmlspecialchars($ponuda['datum_ponude'] ?? '') ?>">

        <label>Važi do</label>
        <input type="date" name="vazi_do" value="<?= htmlspecialchars($ponuda['vazi_do'] ?? '') ?>">

        <button type="submit">Sačuvaj izmene</button>
    </form>
</div>
</body>
</html>
