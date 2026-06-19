<?php
include "config.php";

$izvodjac_id = (int)($_GET['izvodjac_id'] ?? $_POST['izvodjac_id'] ?? 0);

$izvodjac = null;

if ($izvodjac_id > 0) {
    $resIzvodjac = $conn->query("
        SELECT *
        FROM izvodjaci
        WHERE id = $izvodjac_id
        AND aktivan = 1
    ");

    if ($resIzvodjac && $resIzvodjac->num_rows > 0) {
        $izvodjac = $resIzvodjac->fetch_assoc();
    }
}

/* DODAVANJE PONUDE */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dodaj_ponudu'])) {

    $naziv = $_POST['naziv'];
    $datum_ponude = $_POST['datum_ponude'] ?: null;
    $vazi_do = $_POST['vazi_do'] ?: null;
    $dobavljac = $izvodjac ? $izvodjac['naziv'] : $_POST['dobavljac'];

    $stmt = $conn->prepare("
        INSERT INTO ponude
        (naziv, dobavljac, izvodjac_id, datum_ponude, vazi_do)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("ssiss", $naziv, $dobavljac, $izvodjac_id, $datum_ponude, $vazi_do);
    $stmt->execute();

    header("Location: ponude.php" . ($izvodjac_id ? "?izvodjac_id=".$izvodjac_id : ""));
    exit;
}

if ($izvodjac_id > 0) {
    $ponude = $conn->query("
        SELECT *
        FROM ponude
        WHERE aktivna = 1
        AND izvodjac_id = $izvodjac_id
        ORDER BY datum_ponude DESC, naziv
    ");
} else {
    $ponude = $conn->query("
        SELECT *
        FROM ponude
        WHERE aktivna = 1
        ORDER BY datum_ponude DESC, naziv
    ");
}
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Ponude</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="container">

    <h1>Ponude</h1>

    <?php if ($izvodjac): ?>
        <p>Izvođač: <strong><?= htmlspecialchars($izvodjac['naziv']) ?></strong></p>
    <?php endif; ?>

    <div class="grid-2">

        <div>
            <h2>Dodaj ponudu</h2>

            <form method="POST">
                <input type="hidden" name="dodaj_ponudu" value="1">
                <input type="hidden" name="izvodjac_id" value="<?= $izvodjac_id ?>">

                <label>Naziv ponude</label>
                <input type="text" name="naziv" required>

                <?php if (!$izvodjac): ?>
                    <label>Dobavljač</label>
                    <input type="text" name="dobavljac">
                <?php endif; ?>

                <label>Datum ponude</label>
                <input type="date" name="datum_ponude">

                <label>Važi do</label>
                <input type="date" name="vazi_do">

                <button type="submit">Sačuvaj ponudu</button>
            </form>
        </div>

        <div>
            <h2>Lista ponuda</h2>

            <table>
                <tr>
                    <th>Naziv</th>
                    <th>Dobavljač</th>
                    <th>Datum</th>
                    <th>Važi do</th>
                    <th>Akcija</th>
                </tr>

                <?php if ($ponude && $ponude->num_rows > 0): ?>
                    <?php while($p = $ponude->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['naziv']) ?></td>
                            <td><?= htmlspecialchars($p['dobavljac']) ?></td>
                            <td><?= htmlspecialchars($p['datum_ponude']) ?></td>
                            <td><?= htmlspecialchars($p['vazi_do']) ?></td>
                            <td>
                                <a href="ponuda_stavke.php?ponuda_id=<?= $p['id'] ?>">Stavke</a> |
                                <a href="izmeni_ponudu.php?id=<?= $p['id'] ?>">Izmeni</a> |
                                <a href="obrisi_ponudu.php?id=<?= $p['id'] ?>"
                                   onclick="return confirm('Obrisati ponudu?')">Obriši</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">Nema unetih ponuda.</td>
                    </tr>
                <?php endif; ?>

            </table>
        </div>

    </div>

</div>

</body>
</html>
