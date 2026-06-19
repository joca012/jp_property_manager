<?php
include "config.php";

$sz_id = (int)($_GET['sz_id'] ?? $_POST['sz_id'] ?? 0);

$result = $conn->query("SELECT * FROM stambene_zajednice WHERE id = $sz_id");

if (!$result || $result->num_rows == 0) {
    die("Stambena zajednica nije pronađena.");
}

$sz = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $sifarnik_id = (int)$_POST['sifarnik_id'];
    $kolicina = (int)$_POST['kolicina'];
    $napomena = $_POST['napomena'];

    $res = $conn->query("SELECT * FROM sifarnik_opreme WHERE id = $sifarnik_id");

    if (!$res || $res->num_rows == 0) {
        die("Oprema iz šifarnika nije pronađena.");
    }

    $oprema = $res->fetch_assoc();
    $naziv = $oprema['naziv'];

    $stmt = $conn->prepare("
        INSERT INTO oprema_zgrade
        (sz_id, sifarnik_id, naziv, kolicina, napomena)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iisis",
        $sz_id,
        $sifarnik_id,
        $naziv,
        $kolicina,
        $napomena
    );

    $stmt->execute();

    header("Location: oprema.php?sz_id=" . $sz_id);
    exit;
}

$oprema_zgrade = $conn->query("
    SELECT *
    FROM oprema_zgrade
    WHERE sz_id = $sz_id
    AND aktivna = 1
    ORDER BY naziv
");

$sifarnik = $conn->query("
    SELECT *
    FROM sifarnik_opreme
    WHERE aktivna = 1
    ORDER BY naziv
");
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Oprema - <?= htmlspecialchars($sz['naziv']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <h1>Oprema zgrade</h1>

    <p>
        <strong><?= htmlspecialchars($sz['naziv']) ?></strong><br>
        <?= htmlspecialchars($sz['adresa']) ?>
    </p>

    <div class="grid-2">

        <div>
            <h2>Dodaj opremu</h2>

            <form method="POST">

                <input type="hidden" name="sz_id" value="<?= $sz_id ?>">

                <label>Oprema / sistem</label>
                <select name="sifarnik_id" required>
                    <?php while($row = $sifarnik->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>">
                            <?= htmlspecialchars($row['naziv']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Količina</label>
                <input type="number" name="kolicina" value="1" min="1">

                <label>Napomena</label>
                <textarea name="napomena"></textarea>

                <button type="submit">Dodaj opremu</button>

            </form>
        </div>

        <div>
            <h2>Popis opreme</h2>

            <table>
                <tr>
                    <th>Naziv</th>
                    <th>Količina</th>
                    <th>Napomena</th>
                    <th>Akcija</th>
                </tr>

                <?php if ($oprema_zgrade && $oprema_zgrade->num_rows > 0): ?>
                    <?php while($o = $oprema_zgrade->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($o['naziv']) ?></td>
                            <td><?= (int)$o['kolicina'] ?></td>
                            <td><?= nl2br(htmlspecialchars($o['napomena'])) ?></td>
                            <td>
                                <a href="obrisi_opremu.php?id=<?= $o['id'] ?>&sz_id=<?= $sz_id ?>"
                                   onclick="return confirm('Ukloniti ovu opremu iz popisa?')">
                                   Obriši
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">Nema unete opreme za ovu stambenu zajednicu.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

    </div>

    <br>

    <a href="zajednica.php?id=<?= $sz_id ?>">← Nazad na zajednicu</a>

</div>

</body>
</html>