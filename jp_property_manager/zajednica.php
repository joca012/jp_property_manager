<?php
include "config.php";

$id = (int)($_GET['id'] ?? 0);

$result = $conn->query("
    SELECT *
    FROM stambene_zajednice
    WHERE id = $id
");

if (!$result || $result->num_rows == 0) {
    die("Stambena zajednica nije pronađena.");
}

$sz = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($sz['naziv']) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <div style="display:flex;justify-content:space-between;align-items:center;">

        <div>
            <h1><?= htmlspecialchars($sz['naziv']) ?></h1>
            <p><?= htmlspecialchars($sz['adresa']) ?></p>
        </div>

        <div>
            <a class="btn" href="izmeni_zajednicu.php?id=<?= $sz['id'] ?>">
                ✏ Izmeni
            </a>
        </div>

    </div>

    <hr>

    <table>
        <tr>
            <th>Matični broj</th>
            <td><?= htmlspecialchars($sz['maticni_broj']) ?></td>
        </tr>

        <tr>
            <th>PIB</th>
            <td><?= htmlspecialchars($sz['pib']) ?></td>
        </tr>

        <tr>
            <th>Tekući račun</th>
            <td><?= htmlspecialchars($sz['tekuci_racun']) ?></td>
        </tr>

        <tr>
            <th>Broj posebnih delova</th>
            <td><?= $sz['broj_posebnih_delova'] ?></td>
        </tr>

        <tr>
            <th>Broj garažnih mesta</th>
            <td><?= $sz['broj_garaznih_mesta'] ?></td>
        </tr>

        <tr>
            <th>Površina posebnih delova</th>
            <td><?= number_format($sz['povrsina_posebnih_delova'], 2, ',', '.') ?> m²</td>
        </tr>

        <tr>
            <th>Površina garažnih mesta</th>
            <td><?= number_format($sz['povrsina_garaznih_mesta'], 2, ',', '.') ?> m²</td>
        </tr>

        <tr>
            <th>Status</th>
            <td><?= ucfirst($sz['status']) ?></td>
        </tr>

    </table>

    <br>

    <div class="moduli">

        <a class="module-card" href="budzet.php?sz_id=<?= $sz['id'] ?>">
            💰<br>
            Budžet
        </a>

        <a class="module-card" href="oprema.php?sz_id=<?= $sz['id'] ?>">
    🏢<br>
    Oprema
</a>

        <a class="module-card" href="#">
            📅<br>
            Program održavanja
        </a>

        <a class="module-card" href="#">
            🔧<br>
            Kvarovi
        </a>

        <a class="module-card" href="#">
            📁<br>
            Dokumentacija
        </a>

    </div>

    <br>

    <a href="index.php">← Nazad na pregled</a>

</div>

</body>
</html>