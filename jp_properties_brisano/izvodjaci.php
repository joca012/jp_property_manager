<?php
include "config.php";

$sz_id = (int)($_GET['sz_id'] ?? $_POST['sz_id'] ?? 0);
$ctx = $sz_id > 0 ? '?sz_id=' . $sz_id : '';
$ctx_amp = $sz_id > 0 ? '&sz_id=' . $sz_id : '';

$kategorije = [];
$resKat = $conn->query("SELECT id, naziv FROM kategorije_radova WHERE aktivna = 1 ORDER BY naziv");
if ($resKat) {
    while ($row = $resKat->fetch_assoc()) {
        $kategorije[] = $row;
    }
}

function sacuvajKategorijeIzvodjaca(mysqli $conn, int $izvodjac_id, array $kategorije_ids): void
{
    $conn->query("DELETE FROM izvodjac_kategorije WHERE izvodjac_id = $izvodjac_id");

    if (empty($kategorije_ids)) {
        return;
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO izvodjac_kategorije (izvodjac_id, kategorija_id) VALUES (?, ?)");
    foreach ($kategorije_ids as $kat_id) {
        $kat_id = (int)$kat_id;
        if ($kat_id <= 0) {
            continue;
        }
        $stmt->bind_param("ii", $izvodjac_id, $kat_id);
        $stmt->execute();
    }
}

/* DODAVANJE IZVOĐAČA */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dodaj_izvodjaca'])) {
    $naziv = trim($_POST['naziv'] ?? '');
    $pib = trim($_POST['pib'] ?? '');
    $maticni_broj = trim($_POST['maticni_broj'] ?? '');
    $adresa = trim($_POST['adresa'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $napomena = trim($_POST['napomena'] ?? '');
    $kategorije_ids = $_POST['kategorije'] ?? [];

    if ($naziv === '') {
        die("Naziv izvođača je obavezan.");
    }

    $stmt = $conn->prepare("
        INSERT INTO izvodjaci
        (naziv, pib, maticni_broj, adresa, telefon, email, napomena)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssss", $naziv, $pib, $maticni_broj, $adresa, $telefon, $email, $napomena);
    $stmt->execute();

    $izvodjac_id = (int)$conn->insert_id;
    sacuvajKategorijeIzvodjaca($conn, $izvodjac_id, $kategorije_ids);

    header("Location: izvodjaci.php" . $ctx);
    exit;
}

$izvodjaci = $conn->query("
    SELECT i.*,
           GROUP_CONCAT(kr.naziv ORDER BY kr.naziv SEPARATOR ', ') AS kategorije
    FROM izvodjaci i
    LEFT JOIN izvodjac_kategorije ik ON ik.izvodjac_id = i.id
    LEFT JOIN kategorije_radova kr ON kr.id = ik.kategorija_id
    WHERE i.aktivan = 1
    GROUP BY i.id
    ORDER BY i.naziv
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

    <?php if ($sz_id > 0): ?>
        <div class="page-actions"><a class="btn btn-secondary" href="zajednica.php?id=<?= $sz_id ?>">⬅ Nazad na karticu zgrade</a></div>
    <?php endif; ?>

    <div class="grid-2">
        <div>
            <h2>Dodaj izvođača</h2>

            <form method="POST">
                <input type="hidden" name="dodaj_izvodjaca" value="1">
                <input type="hidden" name="sz_id" value="<?= $sz_id ?>">

                <label>Naziv</label>
                <input type="text" name="naziv" required>

                <label>PIB</label>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" name="pib" style="flex:1;">
                    <button type="button" onclick="alert('Uvoz preko PIB-a je predviđen, ali ga treba vezati za zvaničan APR/API servis ili drugi pouzdan izvor. Za sada se podaci unose ručno.')">Uvezi preko PIB-a</button>
                </div>

                <label>Matični broj</label>
                <input type="text" name="maticni_broj">

                <label>Adresa</label>
                <input type="text" name="adresa">

                <label>Kategorije radova</label>
                <div class="checkbox-list">
                    <?php foreach ($kategorije as $k): ?>
                        <label style="display:block; font-weight:normal;">
                            <input type="checkbox" name="kategorije[]" value="<?= (int)$k['id'] ?>">
                            <?= htmlspecialchars($k['naziv']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <label>Telefon</label>
                <input type="text" name="telefon">

                <label>Email</label>
                <input type="email" name="email">

                <label>Napomena</label>
                <textarea name="napomena"></textarea>

                <button class="btn" type="submit">💾 Sačuvaj izvođača</button>
            </form>
        </div>

        <div>
            <h2>Lista izvođača</h2>

            <div class="table-wrap">
            <table>
                <tr>
                    <th>Naziv</th>
                    <th>Kategorije</th>
                    <th>PIB</th>
                    <th>MB</th>
                    <th>Telefon</th>
                    <th>Email</th>
                    <th>Akcija</th>
                </tr>

                <?php if ($izvodjaci && $izvodjaci->num_rows > 0): ?>
                    <?php while($i = $izvodjaci->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($i['naziv']) ?></td>
                            <td><?= htmlspecialchars($i['kategorije'] ?? '') ?></td>
                            <td><?= htmlspecialchars($i['pib'] ?? '') ?></td>
                            <td><?= htmlspecialchars($i['maticni_broj'] ?? '') ?></td>
                            <td><?= htmlspecialchars($i['telefon'] ?? '') ?></td>
                            <td><?= htmlspecialchars($i['email'] ?? '') ?></td>
                            <td>
                                <div class="action-cell">
                                    <a class="btn btn-small" href="ponude.php?izvodjac_id=<?= (int)$i['id'] ?><?= $ctx_amp ?>">📄 Ponude</a>
                                    <a class="btn btn-small" href="izmeni_izvodjaca.php?id=<?= (int)$i['id'] ?><?= $ctx_amp ?>">✏️ Izmeni</a>
                                    <a class="btn btn-small btn-danger" href="obrisi_izvodjaca.php?id=<?= (int)$i['id'] ?><?= $ctx_amp ?>" onclick="return confirm('Obrisati izvođača?')">🗑️ Obriši</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7">Nema unetih izvođača.</td></tr>
                <?php endif; ?>
            </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
