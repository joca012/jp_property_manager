<?php
include "config.php";

/* DODAVANJE IZVOĐAČA */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dodaj_izvodjaca'])) {

    $naziv = $_POST['naziv'];
    $pib = $_POST['pib'];
    $telefon = $_POST['telefon'];
    $email = $_POST['email'];
    $napomena = $_POST['napomena'];

    $stmt = $conn->prepare("
        INSERT INTO izvodjaci
        (naziv, pib, telefon, email, napomena)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("sssss", $naziv, $pib, $telefon, $email, $napomena);
    $stmt->execute();

    header("Location: izvodjaci.php");
    exit;
}

$izvodjaci = $conn->query("
    SELECT *
    FROM izvodjaci
    WHERE aktivan = 1
    ORDER BY naziv
");
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Izvođači</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="container">

    <h1>Izvođači</h1>

    <div class="grid-2">

        <div>
            <h2>Dodaj izvođača</h2>

            <form method="POST">
                <input type="hidden" name="dodaj_izvodjaca" value="1">

                <label>Naziv</label>
                <input type="text" name="naziv" required>

                <label>PIB</label>
                <input type="text" name="pib">

                <label>Telefon</label>
                <input type="text" name="telefon">

                <label>Email</label>
                <input type="email" name="email">

                <label>Napomena</label>
                <textarea name="napomena"></textarea>

                <button type="submit">Sačuvaj izvođača</button>
            </form>
        </div>

        <div>
            <h2>Lista izvođača</h2>

            <table>
                <tr>
                    <th>Naziv</th>
                    <th>PIB</th>
                    <th>Telefon</th>
                    <th>Email</th>
                    <th>Akcija</th>
                </tr>

                <?php if ($izvodjaci && $izvodjaci->num_rows > 0): ?>
                    <?php while($i = $izvodjaci->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($i['naziv']) ?></td>
                            <td><?= htmlspecialchars($i['pib']) ?></td>
                            <td><?= htmlspecialchars($i['telefon']) ?></td>
                            <td><?= htmlspecialchars($i['email']) ?></td>
                            <td>
                                <a href="ponude.php?izvodjac_id=<?= $i['id'] ?>">Ponude</a> |
                                <a href="izmeni_izvodjaca.php?id=<?= $i['id'] ?>">Izmeni</a> |
                                <a href="obrisi_izvodjaca.php?id=<?= $i['id'] ?>"
                                   onclick="return confirm('Obrisati izvođača?')">Obriši</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">Nema unetih izvođača.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

    </div>

</div>

</body>
</html>
