<?php
include "config.php";

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$result = $conn->query("SELECT * FROM ponude WHERE id = $id AND aktivna = 1");

if (!$result || $result->num_rows == 0) {
    die("Ponuda nije pronađena.");
}

$ponuda = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $naziv = $_POST['naziv'];
    $dobavljac = $_POST['dobavljac'];
    $datum_ponude = $_POST['datum_ponude'] ?: null;
    $vazi_do = $_POST['vazi_do'] ?: null;

    $stmt = $conn->prepare("
        UPDATE ponude
        SET naziv = ?, dobavljac = ?, datum_ponude = ?, vazi_do = ?
        WHERE id = ?
    ");

    $stmt->bind_param("ssssi", $naziv, $dobavljac, $datum_ponude, $vazi_do, $id);
    $stmt->execute();

    header("Location: ponude.php");
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

<div class="container narrow">

    <h1>Izmeni ponudu</h1>

    <form method="POST">
        <input type="hidden" name="id" value="<?= $ponuda['id'] ?>">

        <label>Naziv ponude</label>
        <input type="text" name="naziv" value="<?= htmlspecialchars($ponuda['naziv']) ?>" required>

        <label>Dobavljač</label>
        <input type="text" name="dobavljac" value="<?= htmlspecialchars($ponuda['dobavljac']) ?>">

        <label>Datum ponude</label>
        <input type="date" name="datum_ponude" value="<?= htmlspecialchars($ponuda['datum_ponude']) ?>">

        <label>Važi do</label>
        <input type="date" name="vazi_do" value="<?= htmlspecialchars($ponuda['vazi_do']) ?>">

        <button type="submit">Sačuvaj izmene</button>
        <a class="link" href="ponude.php">Nazad</a>
    </form>

</div>

</body>
</html>
