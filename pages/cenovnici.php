<?php
$title = 'Cenovnici';
$subtitle = 'Cenovnici izvođača za planiranje programa održavanja i finansijskog plana.';

$izvodjacNazivCol = first_existing_column($conn, 'izvodjaci', ['naziv', 'ime', 'naziv_firme', 'firma'], 'naziv');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_cenovnik'])) {
    $izvodjacId = (int)($_POST['izvodjac_id'] ?? 0);
    $naziv = trim($_POST['naziv'] ?? '');
    $datumOd = trim($_POST['datum_od'] ?? '');
    $datumDo = trim($_POST['datum_do'] ?? '');

    if ($izvodjacId > 0 && $naziv !== '') {
        $datumOdParam = $datumOd !== '' ? $datumOd : null;
        $datumDoParam = $datumDo !== '' ? $datumDo : null;

        $stmt = $conn->prepare("
            INSERT INTO cenovnici (izvodjac_id, naziv, datum_od, datum_do, aktivan)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->bind_param('isss', $izvodjacId, $naziv, $datumOdParam, $datumDoParam);
        $stmt->execute();

        redirect_to('index.php?page=cenovnici&dodato=1');
    }

    redirect_to('index.php?page=cenovnici&greska=1');
}

if (isset($_GET['deaktiviraj'])) {
    $id = (int)$_GET['deaktiviraj'];

    $stmt = $conn->prepare("UPDATE cenovnici SET aktivan=0 WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    redirect_to('index.php?page=cenovnici&obrisano=1');
}

$izvodjaci = db_all(
    $conn,
    "SELECT id, `$izvodjacNazivCol` AS naziv FROM izvodjaci ORDER BY `$izvodjacNazivCol`"
);

$cenovnici = db_all(
    $conn,
    "SELECT c.*, i.`$izvodjacNazivCol` AS izvodjac_naziv,
            (SELECT COUNT(*) FROM cenovnik_stavke cs WHERE cs.cenovnik_id=c.id AND cs.aktivna=1) AS broj_stavki
     FROM cenovnici c
     JOIN izvodjaci i ON i.id = c.izvodjac_id
     WHERE c.aktivan=1
     ORDER BY i.`$izvodjacNazivCol`, c.datum_od DESC, c.naziv"
);

require __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['dodato'])): ?>
    <section class="card" style="border-left:4px solid green;">Cenovnik je dodat.</section>
<?php endif; ?>

<?php if (isset($_GET['obrisano'])): ?>
    <section class="card" style="border-left:4px solid orange;">Cenovnik je deaktiviran.</section>
<?php endif; ?>

<?php if (isset($_GET['greska'])): ?>
    <section class="card" style="border-left:4px solid red;">Popuni izvođača i naziv cenovnika.</section>
<?php endif; ?>

<section class="card">
    <div class="toolbar">
        <h2>Novi cenovnik</h2>
    </div>

    <form method="post" class="form-grid">
        <input type="hidden" name="dodaj_cenovnik" value="1">

        <div>
            <label>Izvođač</label>
            <select name="izvodjac_id" required>
                <option value="">-- izaberi izvođača --</option>
                <?php foreach ($izvodjaci as $i): ?>
                    <option value="<?= (int)$i['id'] ?>"><?= e($i['naziv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Naziv cenovnika</label>
            <input type="text" name="naziv" required placeholder="npr. Cenovnik 2026">
        </div>

        <div>
            <label>Važi od</label>
            <input type="date" name="datum_od">
        </div>

        <div>
            <label>Važi do</label>
            <input type="date" name="datum_do">
        </div>

        <div>
            <button class="btn btn-primary" type="submit">Dodaj cenovnik</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="toolbar">
        <h2>Aktivni cenovnici</h2>
    </div>

    <?php if (!$cenovnici): ?>
        <div class="empty">Još nema unetih cenovnika.</div>
    <?php else: ?>
        <table class="table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left;">Izvođač</th>
                    <th style="text-align:left;">Cenovnik</th>
                    <th style="text-align:left;">Važi</th>
                    <th style="text-align:center;">Stavke</th>
                    <th style="text-align:right;">Akcije</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cenovnici as $c): ?>
                    <tr>
                        <td><?= e($c['izvodjac_naziv']) ?></td>
                        <td>
                            <a href="index.php?page=cenovnik&id=<?= (int)$c['id'] ?>">
                                <?= e($c['naziv']) ?>
                            </a>
                        </td>
                        <td>
                            <?= e($c['datum_od'] ?: '—') ?> – <?= e($c['datum_do'] ?: '—') ?>
                        </td>
                        <td style="text-align:center;"><?= (int)$c['broj_stavki'] ?></td>
                        <td style="text-align:right;">
                            <a class="btn btn-light btn-sm" href="index.php?page=cenovnik_stavke&cenovnik_id=<?= (int)$c['id'] ?>">Stavke</a>
                            <a class="btn btn-danger btn-sm"
                               href="index.php?page=cenovnici&deaktiviraj=<?= (int)$c['id'] ?>"
                               data-confirm="Deaktivirati cenovnik?">
                                Ukloni
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
