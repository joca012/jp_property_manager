<?php
include "config.php";

$ponuda_id = (int)($_GET['ponuda_id'] ?? $_POST['ponuda_id'] ?? 0);

$resPonuda = $conn->query("
    SELECT *
    FROM ponude
    WHERE id = $ponuda_id
    AND aktivna = 1
");

if (!$resPonuda || $resPonuda->num_rows == 0) {
    die("Ponuda nije pronađena.");
}

$ponuda = $resPonuda->fetch_assoc();

/* DODAVANJE STAVKE PONUDE */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dodaj_stavku'])) {

    $naziv = $_POST['naziv'];
    $obracun = $_POST['obracun'];
    $cena = (float)$_POST['cena'];

    $stmt = $conn->prepare("
        INSERT INTO ponuda_stavke
        (ponuda_id, naziv, obracun, cena)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param("issd", $ponuda_id, $naziv, $obracun, $cena);
    $stmt->execute();

    header("Location: ponuda_stavke.php?ponuda_id=" . $ponuda_id);
    exit;
}

$stavke = $conn->query("
    SELECT *
    FROM ponuda_stavke
    WHERE ponuda_id = $ponuda_id
    AND aktivna = 1
    ORDER BY naziv
");
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Stavke ponude</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="container">

    <h1>Stavke ponude</h1>

    <p>
        <strong><?= htmlspecialchars($ponuda['naziv']) ?></strong><br>
        Dobavljač: <?= htmlspecialchars($ponuda['dobavljac']) ?>
    </p>

    <div class="grid-2">

        <div>
            <h2>Dodaj stavku</h2>

            <form method="POST">
                <input type="hidden" name="dodaj_stavku" value="1">
                <input type="hidden" name="ponuda_id" value="<?= $ponuda_id ?>">

                <label>Naziv stavke</label>
                <input type="text" name="naziv" required>

                <label>Obračun</label>
                <select name="obracun" required>
                    <option value="fiksno">Fiksno</option>
                    <option value="po_komadu">Po komadu</option>
                    <option value="po_m2">Po m²</option>
                    <option value="po_m">Po metru</option>
                    <option value="po_liftu">Po liftu</option>
                    <option value="po_aparatu">Po aparatu</option>
                    <option value="po_hidrantu">Po hidrantu</option>
                    <option value="po_sistemu">Po sistemu</option>
                </select>

                <label>Cena</label>
                <input type="number" step="0.01" name="cena" required>

                <button type="submit">Dodaj stavku</button>
            </form>
        </div>

        <div>
            <h2>Stavke</h2>

            <table>
                <tr>
                    <th>Naziv</th>
                    <th>Obračun</th>
                    <th>Cena</th>
                    <th>Akcija</th>
                </tr>

                <?php if ($stavke && $stavke->num_rows > 0): ?>
                    <?php while($s = $stavke->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['naziv']) ?></td>
                            <td><?= htmlspecialchars($s['obracun']) ?></td>
                            <td><?= number_format($s['cena'], 2, ',', '.') ?> RSD</td>
                            <td>
                                <a href="izmeni_ponuda_stavku.php?id=<?= $s['id'] ?>">Izmeni</a> |
                                <a href="obrisi_ponuda_stavku.php?id=<?= $s['id'] ?>&ponuda_id=<?= $ponuda_id ?>"
                                   onclick="return confirm('Obrisati ovu stavku ponude?')">Obriši</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">Nema stavki za ovu ponudu.</td>
                    </tr>
                <?php endif; ?>

            </table>
        </div>

    </div>

</div>

</body>
</html>
