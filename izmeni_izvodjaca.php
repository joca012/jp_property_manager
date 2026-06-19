<?php
include "config.php";

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$result = $conn->query("SELECT * FROM izvodjaci WHERE id = $id AND aktivan = 1");

if (!$result || $result->num_rows == 0) {
    die("Izvođač nije pronađen.");
}

$izvodjac = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $naziv = $_POST['naziv'];
    $pib = $_POST['pib'];
    $telefon = $_POST['telefon'];
    $email = $_POST['email'];
    $napomena = $_POST['napomena'];

    $stmt = $conn->prepare("
        UPDATE izvodjaci
        SET naziv = ?, pib = ?, telefon = ?, email = ?, napomena = ?
        WHERE id = ?
    ");

    $stmt->bind_param("sssssi", $naziv, $pib, $telefon, $email, $napomena, $id);
    $stmt->execute();

    header("Location: izvodjaci.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Izmeni izvođača</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="container narrow">

    <h1>Izmeni izvođača</h1>

    <form method="POST">
        <input type="hidden" name="id" value="<?= $izvodjac['id'] ?>">

        <label>Naziv</label>
        <input type="text" name="naziv" value="<?= htmlspecialchars($izvodjac['naziv']) ?>" required>

        <label>PIB</label>
        <input type="text" name="pib" value="<?= htmlspecialchars($izvodjac['pib']) ?>">

        <label>Telefon</label>
        <input type="text" name="telefon" value="<?= htmlspecialchars($izvodjac['telefon']) ?>">

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($izvodjac['email']) ?>">

        <label>Napomena</label>
        <textarea name="napomena"><?= htmlspecialchars($izvodjac['napomena']) ?></textarea>

        <button type="submit">Sačuvaj izmene</button>
        <a class="link" href="izvodjaci.php">Nazad</a>
    </form>

</div>

</body>
</html>
