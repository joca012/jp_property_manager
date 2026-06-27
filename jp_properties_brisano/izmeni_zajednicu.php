<?php
include "config.php";

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$result = $conn->query("SELECT * FROM stambene_zajednice WHERE id = $id");

if (!$result || $result->num_rows == 0) {
    die("Stambena zajednica nije pronađena.");
}

$sz = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $naziv = $_POST['naziv'];
    $adresa = $_POST['adresa'];
    $grad = $_POST['grad'];
    $maticni_broj = $_POST['maticni_broj'];
    $pib = $_POST['pib'];
    $tekuci_racun = $_POST['tekuci_racun'];

    $broj_posebnih_delova = (int)$_POST['broj_posebnih_delova'];
    $broj_garaznih_mesta = (int)$_POST['broj_garaznih_mesta'];

    $povrsina_posebnih_delova = (float)$_POST['povrsina_posebnih_delova'];
    $povrsina_garaznih_mesta = (float)$_POST['povrsina_garaznih_mesta'];

    $status = $_POST['status'];

    $stmt = $conn->prepare("
        UPDATE stambene_zajednice
        SET
            naziv = ?,
            adresa = ?,
            grad = ?,
            maticni_broj = ?,
            pib = ?,
            tekuci_racun = ?,
            broj_posebnih_delova = ?,
            broj_garaznih_mesta = ?,
            povrsina_posebnih_delova = ?,
            povrsina_garaznih_mesta = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssssssiiddsi",
        $naziv,
        $adresa,
        $grad,
        $maticni_broj,
        $pib,
        $tekuci_racun,
        $broj_posebnih_delova,
        $broj_garaznih_mesta,
        $povrsina_posebnih_delova,
        $povrsina_garaznih_mesta,
        $status,
        $id
    );

    $stmt->execute();

    header("Location: zajednica.php?id=" . $id);
    exit;
}
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Izmeni stambenu zajednicu</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container narrow">

    <h1>Izmeni stambenu zajednicu</h1>

    <form method="POST">

        <input type="hidden" name="id" value="<?= $sz['id'] ?>">

        <label>Naziv</label>
        <input type="text" name="naziv" value="<?= htmlspecialchars($sz['naziv']) ?>" required>

        <label>Adresa</label>
        <input type="text" name="adresa" value="<?= htmlspecialchars($sz['adresa']) ?>">

        <label>Grad</label>
        <input type="text" name="grad" value="<?= htmlspecialchars($sz['grad']) ?>">

        <label>Matični broj</label>
        <input type="text" name="maticni_broj" value="<?= htmlspecialchars($sz['maticni_broj']) ?>">

        <label>PIB</label>
        <input type="text" name="pib" value="<?= htmlspecialchars($sz['pib']) ?>">

        <label>Tekući račun</label>
        <input type="text" name="tekuci_racun" value="<?= htmlspecialchars($sz['tekuci_racun']) ?>">

        <label>Broj posebnih delova</label>
        <input type="number" name="broj_posebnih_delova" value="<?= $sz['broj_posebnih_delova'] ?>">

        <label>Broj garažnih mesta</label>
        <input type="number" name="broj_garaznih_mesta" value="<?= $sz['broj_garaznih_mesta'] ?>">

        <label>Ukupna površina posebnih delova</label>
        <input type="number" step="0.01" name="povrsina_posebnih_delova" value="<?= $sz['povrsina_posebnih_delova'] ?>">

        <label>Ukupna površina garažnih mesta</label>
        <input type="number" step="0.01" name="povrsina_garaznih_mesta" value="<?= $sz['povrsina_garaznih_mesta'] ?>">

        <label>Status</label>
        <select name="status">
            <option value="aktivna" <?= $sz['status'] == 'aktivna' ? 'selected' : '' ?>>Aktivna</option>
            <option value="neaktivna" <?= $sz['status'] == 'neaktivna' ? 'selected' : '' ?>>Neaktivna</option>
        </select>

        <button type="submit">Sačuvaj izmene</button>
        <a class="link" href="zajednica.php?id=<?= $sz['id'] ?>">Nazad</a>

    </form>

</div>

</body>
</html>