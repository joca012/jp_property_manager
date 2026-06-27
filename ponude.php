<?php
include "config.php";

$sz_id = (int)($_GET['sz_id'] ?? $_POST['sz_id'] ?? 0);
$ctx = $sz_id > 0 ? '?sz_id=' . $sz_id : '';
$ctx_amp = $sz_id > 0 ? '&sz_id=' . $sz_id : '';
$izvodjac_id = (int)($_GET['izvodjac_id'] ?? $_POST['izvodjac_id'] ?? 0);

$izvodjaciLista = [];
$resIzvodjaci = $conn->query("
    SELECT i.id, i.naziv,
           GROUP_CONCAT(kr.naziv ORDER BY kr.naziv SEPARATOR ', ') AS kategorije
    FROM izvodjaci i
    LEFT JOIN izvodjac_kategorije ik ON ik.izvodjac_id = i.id
    LEFT JOIN kategorije_radova kr ON kr.id = ik.kategorija_id
    WHERE i.aktivan = 1
    GROUP BY i.id
    ORDER BY i.naziv
");
if ($resIzvodjaci) {
    while ($row = $resIzvodjaci->fetch_assoc()) {
        $izvodjaciLista[] = $row;
    }
}

function nadjiIzvodjaca(array $lista, int $id): ?array
{
    foreach ($lista as $i) {
        if ((int)$i['id'] === $id) {
            return $i;
        }
    }
    return null;
}

$izvodjac = $izvodjac_id > 0 ? nadjiIzvodjaca($izvodjaciLista, $izvodjac_id) : null;

function kategorijeIzvodjacaTekst(mysqli $conn, int $izvodjac_id): string
{
    $res = $conn->query("
        SELECT GROUP_CONCAT(kr.naziv ORDER BY kr.naziv SEPARATOR ', ') AS kategorije
        FROM izvodjac_kategorije ik
        JOIN kategorije_radova kr ON kr.id = ik.kategorija_id
        WHERE ik.izvodjac_id = $izvodjac_id
    ");
    if ($res && $row = $res->fetch_assoc()) {
        return $row['kategorije'] ?? '';
    }
    return '';
}

/* DODAVANJE PONUDE */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dodaj_ponudu'])) {
    $naziv = trim($_POST['naziv'] ?? '');
    $izvodjac_id = (int)($_POST['izvodjac_id'] ?? 0);
    $datum_ponude = $_POST['datum_ponude'] ?: null;
    $vazi_do = $_POST['vazi_do'] ?: null;

    if ($naziv === '') {
        die("Naziv ponude je obavezan.");
    }
    if ($izvodjac_id <= 0) {
        die("Izvođač je obavezan.");
    }

    $resIzvodjac = $conn->query("SELECT naziv FROM izvodjaci WHERE id = $izvodjac_id AND aktivan = 1");
    if (!$resIzvodjac || $resIzvodjac->num_rows == 0) {
        die("Izabrani izvođač nije pronađen.");
    }
    $izvodjacRow = $resIzvodjac->fetch_assoc();
    $izvodjac_naziv = $izvodjacRow['naziv'];

    // Stara kolona ponude.kategorija ostaje popunjena tekstom kategorija izvođača radi kompatibilnosti prikaza.
    // Logika filtriranja ubuduće ide preko izvodjac_kategorije.
    $kategorija_tekst = kategorijeIzvodjacaTekst($conn, $izvodjac_id);

    $stmt = $conn->prepare("
        INSERT INTO ponude
        (naziv, kategorija, dobavljac, izvodjac_id, sz_id, datum_ponude, vazi_do)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssiiss", $naziv, $kategorija_tekst, $izvodjac_naziv, $izvodjac_id, $sz_id, $datum_ponude, $vazi_do);
    $stmt->execute();

    $redirect = "ponude.php";
    $params = [];
    if ($izvodjac_id > 0) { $params[] = "izvodjac_id=" . $izvodjac_id; }
    if ($sz_id > 0) { $params[] = "sz_id=" . $sz_id; }
    if (!empty($params)) { $redirect .= "?" . implode("&", $params); }
    header("Location: " . $redirect);
    exit;
}

$where = "p.aktivna = 1";
if ($izvodjac_id > 0) {
    $where .= " AND p.izvodjac_id = $izvodjac_id";
}

$ponude = $conn->query("
    SELECT p.*, i.naziv AS izvodjac_naziv,
           GROUP_CONCAT(kr.naziv ORDER BY kr.naziv SEPARATOR ', ') AS kategorije_izvodjaca,
           COUNT(DISTINCT pd.id) AS broj_dokumenata
    FROM ponude p
    LEFT JOIN izvodjaci i ON i.id = p.izvodjac_id
    LEFT JOIN izvodjac_kategorije ik ON ik.izvodjac_id = i.id
    LEFT JOIN kategorije_radova kr ON kr.id = ik.kategorija_id
    LEFT JOIN ponuda_dokumenti pd ON pd.ponuda_id = p.id
    WHERE $where
    GROUP BY p.id
    ORDER BY p.datum_ponude DESC, p.naziv
");
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Ponude</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include "header.php"; ?>

<div class="container">
    <h1>📄 Ponude</h1>

    <?php if ($sz_id > 0): ?>
        <div class="page-actions">
            <a class="btn btn-secondary" href="zajednica.php?id=<?= $sz_id ?>">⬅ Nazad na karticu zgrade</a>
        </div>
    <?php endif; ?>

    <?php if ($izvodjac): ?>
        <div class="card">
            <strong>Izvođač:</strong> <?= htmlspecialchars($izvodjac['naziv']) ?><br>
            <strong>Kategorije:</strong> <?= htmlspecialchars($izvodjac['kategorije'] ?? '') ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>➕ Dodaj ponudu</h2>

        <form method="POST">
            <input type="hidden" name="dodaj_ponudu" value="1">
            <input type="hidden" name="sz_id" value="<?= $sz_id ?>">

            <div class="form-grid">
                <div>
                    <label>Naziv ponude</label>
                    <input type="text" name="naziv" required>
                </div>

                <div>
                    <label>Izvođač</label>
                    <select name="izvodjac_id" required>
                        <option value="">-- Izaberi izvođača --</option>
                        <?php foreach ($izvodjaciLista as $i): ?>
                            <option value="<?= (int)$i['id'] ?>" <?= ((int)$i['id'] === $izvodjac_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($i['naziv']) ?><?= !empty($i['kategorije']) ? ' — ' . htmlspecialchars($i['kategorije']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Datum ponude</label>
                    <input type="date" name="datum_ponude">
                </div>

                <div>
                    <label>Važi do</label>
                    <input type="date" name="vazi_do">
                </div>

                <div>
                    <button class="btn" type="submit">💾 Sačuvaj ponudu</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Lista ponuda</h2>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>Naziv</th>
                    <th>Izvođač</th>
                    <th>Kategorije izvođača</th>
                    <th>Datum</th>
                    <th>Važi do</th>
                    <th>Dokumenti</th>
                    <th>Akcija</th>
                </tr>

                <?php if ($ponude && $ponude->num_rows > 0): ?>
                    <?php while($p = $ponude->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['naziv']) ?></td>
                            <td><?= htmlspecialchars($p['izvodjac_naziv'] ?: ($p['dobavljac'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($p['kategorije_izvodjaca'] ?: ($p['kategorija'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($p['datum_ponude'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['vazi_do'] ?? '') ?></td>
                            <td><?= (int)($p['broj_dokumenata'] ?? 0) ?></td>
                            <td>
                                <div class="action-cell">
                                    <a class="btn btn-small" href="ponuda_stavke.php?ponuda_id=<?= (int)$p['id'] ?><?= $ctx_amp ?>">🧾 Stavke</a>
                                    <a class="btn btn-small" href="ponuda_dokumenti.php?ponuda_id=<?= (int)$p['id'] ?><?= $ctx_amp ?>">📎 Dokumenta</a>
                                    <a class="btn btn-small" href="izmeni_ponudu.php?id=<?= (int)$p['id'] ?><?= $ctx_amp ?>">✏️ Izmeni</a>
                                    <a class="btn btn-small btn-danger" href="obrisi_ponudu.php?id=<?= (int)$p['id'] ?><?= $ctx_amp ?>" onclick="return confirm('Obrisati ponudu?')">🗑️ Obriši</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7">Nema unetih ponuda.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
