<?php
include "config.php";

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$result = $conn->query("
    SELECT *
    FROM ponuda_stavke
    WHERE id = $id
    AND aktivna = 1
");

if (!$result || $result->num_rows == 0) {
    die("Stavka ponude nije pronađena.");
}

$stavka = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $naziv = $_POST['naziv'];
    $obracun = $_POST['obracun'];
    $cena = (float)$_POST['cena'];

    $stmt = $conn->prepare("
        UPDATE ponuda_stavke
        SET naziv = ?, obracun = ?, cena = ?
        WHERE id = ?
    ");

    $stmt->bind_param("ssdi", $naziv, $obracun, $cena, $id);
    $stmt->execute();

    header("Location: ponuda_stavke.php?ponuda_id=" . $stavka['ponuda_id']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Izmeni stavku ponude</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="container narrow">

    <h1>Izmeni stavku ponude</h1>

    <form method="POST">
        <input type="hidden" name="id" value="<?= $stavka['id'] ?>">

        <label>Naziv stavke</label>
        <input type="text" name="naziv" value="<?= htmlspecialchars($stavka['naziv']) ?>" required>

        <label>Obračun</label>
        <select name="obracun" required>
            <?php
            $opcije = [
                'fiksno' => 'Fiksno',
                'po_komadu' => 'Po komadu',
                'po_m2' => 'Po m²',
                'po_m' => 'Po metru',
                'po_liftu' => 'Po liftu',
                'po_aparatu' => 'Po aparatu',
                'po_hidrantu' => 'Po hidrantu',
                'po_sistemu' => 'Po sistemu'
            ];

            foreach ($opcije as $value => $label):
            ?>
                <option value="<?= $value ?>" <?= $stavka['obracun'] == $value ? 'selected' : '' ?>>
                    <?= $label ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Cena</label>
        <input type="number" step="0.01" name="cena" value="<?= $stavka['cena'] ?>" required>

        <button type="submit">Sačuvaj izmene</button>
        <a class="link" href="ponuda_stavke.php?ponuda_id=<?= $stavka['ponuda_id'] ?>">Nazad</a>
    </form>

</div>

</body>
</html>
