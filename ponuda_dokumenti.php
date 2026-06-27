<?php
include "config.php";

$ponuda_id = (int)($_GET['ponuda_id'] ?? $_POST['ponuda_id'] ?? 0);
$sz_id = (int)($_GET['sz_id'] ?? $_POST['sz_id'] ?? 0);
$ctx_amp = $sz_id > 0 ? '&sz_id=' . $sz_id : '';
$ctx_q = $sz_id > 0 ? '?sz_id=' . $sz_id : '';

if ($ponuda_id <= 0) {
    die("Ponuda nije pronađena.");
}

$ponudaRes = $conn->query("
    SELECT p.*, COALESCE(i.naziv, p.dobavljac) AS izvodjac
    FROM ponude p
    LEFT JOIN izvodjaci i ON i.id = p.izvodjac_id
    WHERE p.id = $ponuda_id
    LIMIT 1
");

if (!$ponudaRes || $ponudaRes->num_rows == 0) {
    die("Ponuda nije pronađena.");
}

$ponuda = $ponudaRes->fetch_assoc();

function jp_safe_file_name(string $name): string
{
    $name = basename($name);
    $name = preg_replace('/[^A-Za-z0-9_\.\-]+/', '_', $name);
    return trim($name, '._') ?: 'dokument';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_dokument'])) {
    if (!isset($_FILES['dokument']) || $_FILES['dokument']['error'] !== UPLOAD_ERR_OK) {
        die("Greška pri uploadu dokumenta.");
    }

    $file = $_FILES['dokument'];
    $maxSize = 10 * 1024 * 1024; // 10 MB
    if ((int)$file['size'] > $maxSize) {
        die("Dokument je veći od 10 MB.");
    }

    $originalName = jp_safe_file_name($file['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowed, true)) {
        die("Dozvoljeni su samo PDF i slike: jpg, jpeg, png, webp.");
    }

    $uploadDir = __DIR__ . '/uploads/ponude';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $storedName = 'ponuda_' . $ponuda_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $fullPath = $uploadDir . '/' . $storedName;
    $relativePath = 'uploads/ponude/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        die("Dokument nije sačuvan.");
    }

    $mime = $file['type'] ?? '';
    $size = (int)$file['size'];
    $stmt = $conn->prepare("INSERT INTO ponuda_dokumenti (ponuda_id, naziv_fajla, putanja, mime_type, velicina) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $ponuda_id, $originalName, $relativePath, $mime, $size);
    $stmt->execute();

    header("Location: ponuda_dokumenti.php?ponuda_id=$ponuda_id$ctx_amp");
    exit;
}

if (isset($_GET['obrisi'])) {
    $doc_id = (int)$_GET['obrisi'];
    $docRes = $conn->query("SELECT * FROM ponuda_dokumenti WHERE id = $doc_id AND ponuda_id = $ponuda_id LIMIT 1");
    if ($docRes && $docRes->num_rows > 0) {
        $doc = $docRes->fetch_assoc();
        $filePath = __DIR__ . '/' . $doc['putanja'];
        if (is_file($filePath)) {
            unlink($filePath);
        }
        $conn->query("DELETE FROM ponuda_dokumenti WHERE id = $doc_id AND ponuda_id = $ponuda_id");
    }

    header("Location: ponuda_dokumenti.php?ponuda_id=$ponuda_id$ctx_amp");
    exit;
}

$dokumenti = $conn->query("SELECT * FROM ponuda_dokumenti WHERE ponuda_id = $ponuda_id ORDER BY created_at DESC, id DESC");
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Dokumenta ponude</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include "header.php"; ?>

<div class="container">
    <h1>📎 Dokumenta ponude</h1>

    <div class="card">
        <strong>Ponuda:</strong> <?= htmlspecialchars($ponuda['naziv']) ?><br>
        <strong>Izvođač:</strong> <?= htmlspecialchars($ponuda['izvodjac'] ?? '') ?>
    </div>

    <div class="page-actions">
        <a class="btn btn-secondary" href="ponude.php<?= $ctx_q ?>">⬅ Nazad na ponude</a>
        <a class="btn btn-secondary" href="ponuda_stavke.php?ponuda_id=<?= $ponuda_id ?><?= $ctx_amp ?>">🧾 Stavke ponude</a>
    </div>

    <div class="card">
        <h2>Upload dokumenta</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="ponuda_id" value="<?= $ponuda_id ?>">
            <input type="hidden" name="sz_id" value="<?= $sz_id ?>">
            <input type="file" name="dokument" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
            <button class="btn" type="submit" name="upload_dokument">📤 Sačuvaj dokument</button>
        </form>
        <small>Dozvoljeno: PDF i slike, do 10 MB.</small>
    </div>

    <div class="card">
        <h2>Sačuvana dokumenta</h2>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>Dokument</th>
                    <th>Tip</th>
                    <th>Veličina</th>
                    <th>Datum</th>
                    <th>Akcija</th>
                </tr>
                <?php if ($dokumenti && $dokumenti->num_rows > 0): ?>
                    <?php while ($d = $dokumenti->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($d['naziv_fajla']) ?></td>
                            <td><?= htmlspecialchars($d['mime_type'] ?? '') ?></td>
                            <td><?= number_format(((int)$d['velicina']) / 1024, 1, ',', '.') ?> KB</td>
                            <td><?= htmlspecialchars($d['created_at'] ?? '') ?></td>
                            <td>
                                <div class="action-cell">
                                    <a class="btn btn-small" href="<?= htmlspecialchars($d['putanja']) ?>" target="_blank">👁️ Otvori</a>
                                    <a class="btn btn-small btn-danger" href="ponuda_dokumenti.php?ponuda_id=<?= $ponuda_id ?>&obrisi=<?= (int)$d['id'] ?><?= $ctx_amp ?>" onclick="return confirm('Obrisati dokument?')">🗑️ Obriši</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">Nema dokumenata za ovu ponudu.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
