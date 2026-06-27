<?php
include "config.php";

$budzet_id = (int)($_GET['budzet_id'] ?? $_POST['budzet_id'] ?? 0);

$result = $conn->query("
    SELECT b.*, sz.*
    FROM budzeti b
    JOIN stambene_zajednice sz ON sz.id = b.sz_id
    WHERE b.id = $budzet_id
");

if (!$result || $result->num_rows == 0) {
    die("Budžet nije pronađen.");
}

$podaci = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $sifarnik_id = (int)$_POST['sifarnik_id'];
    $iznos = (float)$_POST['iznos'];

    $res = $conn->query("
        SELECT *
        FROM sifarnik_budzet_stavki
        WHERE id = $sifarnik_id
    ");

    if (!$res || $res->num_rows == 0) {
        die("Stavka iz šifarnika nije pronađena.");
    }

    $s = $res->fetch_assoc();

    $stmt = $conn->prepare("
        INSERT INTO budzet_stavke
        (
            budzet_id,
            sifarnik_id,
            naziv,
            vrsta,
            obracun,
            ucestalost,
            iznos
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iissssd",
        $budzet_id,
        $sifarnik_id,
        $s['naziv'],
        $s['vrsta'],
        $s['obracun'],
        $s['ucestalost'],
        $iznos
    );

    $stmt->execute();

    header("Location: budzet_stavke.php?budzet_id=" . $budzet_id);
    exit;
}

function godisnjiIznos($stavka, $podaci) {

    $osnovica = 1;

    if ($stavka['obracun'] == 'po_posebnom_delu') {
        $osnovica = (int)$podaci['broj_posebnih_delova'];
    }

    if ($stavka['obracun'] == 'po_m2') {
        $osnovica = (float)$podaci['povrsina_posebnih_delova'];
    }

    if ($stavka['obracun'] == 'po_garaznom_mestu') {
        $osnovica = (int)$podaci['broj_garaznih_mesta'];
    }

    $mnozilac = 1;

    if ($stavka['ucestalost'] == 'mesecno') {
        $mnozilac = 12;
    }

    return $stavka['iznos'] * $osnovica * $mnozilac;
}

$stavke = $conn->query("
    SELECT *
    FROM budzet_stavke
    WHERE budzet_id = $budzet_id
    AND aktivna = 1
    ORDER BY naziv
");

$sifarnik = $conn->query("
    SELECT *
    FROM sifarnik_budzet_stavki
    WHERE aktivna = 1
    ORDER BY naziv
");
?>

<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<title>Stavke budžeta</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <h1>Stavke budžeta</h1>

    <p>
        <strong><?= htmlspecialchars($podaci['naziv']) ?></strong><br>
        Budžet: <?= htmlspecialchars($podaci['naziv']) ?> / <?= htmlspecialchars($podaci['godina']) ?>
    </p>

    <div class="grid-2">

        <div>
            <h2>Dodaj stavku</h2>

            <form method="POST">
                <input type="hidden" name="budzet_id" value="<?= $budzet_id ?>">

                <label>Stavka</label>
                <select name="sifarnik_id" required>
                    <?php while($row = $sifarnik->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>">
                            <?= htmlspecialchars($row['naziv']) ?>
                            —
                            <?= $row['vrsta'] ?>,
                            <?= $row['obracun'] ?>,
                            <?= $row['ucestalost'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Iznos</label>
                <input type="number" step="0.01" name="iznos" required>

                <button type="submit">Dodaj stavku</button>
            </form>
        </div>

        <div>
            <h2>Budžetske stavke</h2>

            <table>
                <tr>
                    <th>Naziv</th>
                    <th>Vrsta</th>
                    <th>Obračun</th>
                    <th>Učestalost</th>
                    <th>Iznos</th>
                    <th>Godišnje</th>
                    <th>Akcija</th>
                </tr>

                <?php
                $ukupno_prilivi = 0;
                $ukupno_odlivi = 0;

                if ($stavke && $stavke->num_rows > 0):
                    while($s = $stavke->fetch_assoc()):

                        $godisnje = godisnjiIznos($s, $podaci);

                        if($s['vrsta'] == 'priliv'){
                            $ukupno_prilivi += $godisnje;
                        } else {
                            $ukupno_odlivi += $godisnje;
                        }
                ?>

                <tr>
                    <td><?= htmlspecialchars($s['naziv']) ?></td>
                    <td><?= $s['vrsta'] ?></td>
                    <td><?= $s['obracun'] ?></td>
                    <td><?= $s['ucestalost'] ?></td>
                    <td><?= number_format($s['iznos'], 2, ',', '.') ?></td>
                    <td><strong><?= number_format($godisnje, 2, ',', '.') ?></strong></td>
                    <td>
                        <a href="obrisi_budzet_stavku.php?id=<?= $s['id'] ?>&budzet_id=<?= $budzet_id ?>"
                           onclick="return confirm('Obrisati ovu stavku?')">
                           Obriši
                        </a>
                    </td>
                </tr>

                <?php
                    endwhile;
                else:
                ?>

                <tr>
                    <td colspan="7">Nema unetih stavki budžeta.</td>
                </tr>

                <?php endif; ?>

            </table>

            <br>

            <p>Ukupno prilivi godišnje: <strong><?= number_format($ukupno_prilivi, 2, ',', '.') ?> RSD</strong></p>
            <p>Ukupno odlivi godišnje: <strong><?= number_format($ukupno_odlivi, 2, ',', '.') ?> RSD</strong></p>

            <hr>

            <p>
                Razlika:
                <strong><?= number_format($ukupno_prilivi - $ukupno_odlivi, 2, ',', '.') ?> RSD</strong>
            </p>
        </div>

    </div>

    <br>

    <a href="budzet.php?sz_id=<?= $podaci['sz_id'] ?>">← Nazad na budžet</a>

</div>

</body>
</html>