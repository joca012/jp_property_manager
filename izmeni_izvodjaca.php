<?php
include "config.php";

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$sz_id = (int)($_GET['sz_id'] ?? $_POST['sz_id'] ?? 0);
$ctx = $sz_id > 0 ? '?sz_id=' . $sz_id : '';


function sacuvajKategorijeIzvodjaca(mysqli $conn, int $izvodjac_id, array $kategorije_ids): void
{
    $conn->query("DELETE FROM izvodjac_kategorije WHERE izvodjac_id = $izvodjac_id");
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

$result = $conn->query("SELECT * FROM izvodjaci WHERE id = $id AND aktivan = 1");
if (!$result || $result->num_rows == 0) {
    die("Izvođač nije pronađen.");
}
$izvodjac = $result->fetch_assoc();

$kategorije = [];
$resKat = $conn->query("SELECT id, naziv FROM kategorije_radova WHERE aktivna = 1 ORDER BY naziv");
if ($resKat) {
    while ($row = $resKat->fetch_assoc()) {
        $kategorije[] = $row;
    }
}

$izabrane = [];
$resIzabrane = $conn->query("SELECT kategorija_id FROM izvodjac_kategorije WHERE izvodjac_id = $id");
if ($resIzabrane) {
    while ($row = $resIzabrane->fetch_assoc()) {
        $izabrane[] = (int)$row['kategorija_id'];
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
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
        UPDATE izvodjaci
        SET naziv = ?, pib = ?, maticni_broj = ?, adresa = ?, telefon = ?, email = ?, napomena = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssssssi", $naziv, $pib, $maticni_broj, $adresa, $telefon, $email, $napomena, $id);
    $stmt->execute();

    sacuvajKategorijeIzvodjaca($conn, $id, $kategorije_ids);

    header("Location: izvodjaci.php" . $ctx);
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

<div class="container">
    <h1>Izmeni izvođača</h1>

    <?php if ($sz_id > 0): ?>
        <p><a href="izvodjaci.php?sz_id=<?= $sz_id ?>">← Nazad na izvođače</a></p>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="sz_id" value="<?= $sz_id ?>">

        <label>Naziv</label>
        <input type="text" name="naziv" value="<?= htmlspecialchars($izvodjac['naziv'] ?? '') ?>" required>

        <label>PIB</label>
        <input type="text" name="pib" value="<?= htmlspecialchars($izvodjac['pib'] ?? '') ?>">

        <label>Matični broj</label>
        <input type="text" name="maticni_broj" value="<?= htmlspecialchars($izvodjac['maticni_broj'] ?? '') ?>">

        <label>Adresa</label>
        <input type="text" name="adresa" value="<?= htmlspecialchars($izvodjac['adresa'] ?? '') ?>">

        <label>Kategorije radova</label>
        <div class="checkbox-list">
            <?php foreach ($kategorije as $k): ?>
                <label style="display:block; font-weight:normal;">
                    <input type="checkbox" name="kategorije[]" value="<?= (int)$k['id'] ?>" <?= in_array((int)$k['id'], $izabrane, true) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($k['naziv']) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <label>Telefon</label>
        <input type="text" name="telefon" value="<?= htmlspecialchars($izvodjac['telefon'] ?? '') ?>">

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($izvodjac['email'] ?? '') ?>">

        <label>Napomena</label>
        <textarea name="napomena"><?= htmlspecialchars($izvodjac['napomena'] ?? '') ?></textarea>

        <button type="submit">Sačuvaj izmene</button>
    </form>
</div>
</body>
</html>
