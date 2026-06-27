<?php
include "config.php";

$result = $conn->query("
    SELECT *
    FROM stambene_zajednice
    ORDER BY naziv ASC
");
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>JP Property Manager</title>
	<div style="margin-bottom:20px;">

    <a class="btn" href="izvodjaci.php">
        🏭 Izvođači
    </a>

    <a class="btn" href="ponude.php">
        📄 Ponude
    </a>

</div>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1>JP Property Manager</h1>

        <a class="btn" href="dodaj_zajednicu.php">
            + Nova stambena zajednica
        </a>
    </div>

    <table>

        <tr>
            <th>Naziv</th>
            <th>Adresa</th>
            <th>Posebni delovi</th>
            <th>Garažna mesta</th>
            <th>Akcija</th>
        </tr>

        <?php while($row = $result->fetch_assoc()): ?>

        <tr>

            <td><?= htmlspecialchars($row['naziv']) ?></td>

            <td><?= htmlspecialchars($row['adresa']) ?></td>

            <td><?= $row['broj_posebnih_delova'] ?></td>

            <td><?= $row['broj_garaznih_mesta'] ?></td>

            <td>
                <a href="zajednica.php?id=<?= $row['id'] ?>">
                    Otvori
                </a>
            </td>

        </tr>

        <?php endwhile; ?>

    </table>

</div>

</body>
</html>