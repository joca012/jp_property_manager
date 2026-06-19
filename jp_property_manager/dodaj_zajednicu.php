<?php
include "config.php";

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

    $stmt = $conn->prepare("
        INSERT INTO stambene_zajednice
        (
            naziv, adresa, grad, maticni_broj, pib, tekuci_racun,
            broj_posebnih_delova, broj_garaznih_mesta,
            povrsina_posebnih_delova, povrsina_garaznih_mesta
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssssssiidd",
        $naziv,
        $adresa,
        $grad,
        $maticni_broj,
        $pib,
        $tekuci_racun,
        $broj_posebnih_delova,
        $broj_garaznih_mesta,
        $povrsina_posebnih_delova,
        $povrsina_garaznih_mesta
    );

    $stmt->execute();

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Dodaj stambenu zajednicu</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container narrow">

    <h1>Nova stambena zajednica</h1>

    <form method="POST">

        <label>Naziv</label>
        <input type="text" name="naziv" required>

        <label>Adresa</label>
        <input type="text" name="adresa">

        <label>Grad</label>
        <input type="text" name="grad">

        <label>Matični broj</label>
        <input type="text" name="maticni_broj">

        <label>PIB</label>
        <input type="text" name="pib">

        <label>Tekući račun</label>
        <input type="text" name="tekuci_racun">

        <label>Broj posebnih delova</label>
        <input type="number" name="broj_posebnih_delova" value="0">

        <label>Broj garažnih mesta</label>
        <input type="number" name="broj_garaznih_mesta" value="0">

        <label>Ukupna površina posebnih delova</label>
        <input type="number" step="0.01" name="povrsina_posebnih_delova" value="0">

        <label>Ukupna površina garažnih mesta</label>
        <input type="number" step="0.01" name="povrsina_garaznih_mesta" value="0">

        <button type="submit">Sačuvaj</button>
        <a class="link" href="index.php">Nazad</a>

    </form>

</div>

</body>
</html>