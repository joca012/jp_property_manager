<?php
$id = get_int('id');

$title = 'Cenovnik';
$subtitle = 'Zaglavlje cenovnika i osnovni podaci.';

if ($id <= 0) {
    die('Nije izabran cenovnik.');
}

$izvodjacNazivCol = first_existing_column($conn, 'izvodjaci', ['naziv', 'ime', 'naziv_firme', 'firma'], 'naziv');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['snimi_cenovnik'])) {
    $naziv = trim($_POST['naziv'] ?? '');
    $datumOd = trim($_POST['datum_od'] ?? '');
    $datumDo = trim($_POST['datum_do'] ?? '');

    if ($naziv !== '') {
        $datumOdParam = $datumOd !== '' ? $datumOd : null;
        $datumDoParam = $datumDo !== '' ? $datumDo : null;

        $stmt = $conn->prepare("
            UPDATE cenovnici
            SET naziv=?, datum_od=?, datum_do=?
            WHERE id=?
        ");
        $stmt->bind_param('sssi', $naziv, $datumOdParam, $datumDoParam, $id);
        $stmt->execute();

        redirect_to('index.php?page=cenovnik&id=' . $id . '&snimljeno=1');
    }
}

$cenovnik = db_one(
    $conn,
    "SELECT c.*, i.`$izvodjacNazivCol` AS izvodjac_naziv
     FROM cenovnici c
     JOIN izvodjaci i ON i.id=c.izvodjac_id
     WHERE c.id=?",
    'i',
    [$id]
);

if (!$cenovnik) {
    die('Cenovnik nije pronađen.');
}

$stavke = db_all(
    $conn,
    "SELECT cs.*, se.naziv AS element_naziv, sa.naziv AS aktivnost_naziv, oj.oznaka AS jedinica_oznaka
     FROM cenovnik_stavke cs
     JOIN sifarnik_elemenata se ON se.id=cs.element_id
     JOIN sifarnik_aktivnosti sa ON sa.id=cs.aktivnost_id
     JOIN obracunske_jedinice oj ON oj.id=cs.jedinica_id
     WHERE cs.cenovnik_id=? AND cs.aktivna=1
     ORDER BY se.naziv, sa.naziv",
    'i',
    [$id]
);

require __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['snimljeno'])): ?>
    <section class="card" style="border-left:4px solid green;">Cenovnik je sačuvan.</section>
<?php endif; ?>

<section class="card">
    <div class="toolbar">
        <h2><?= e($cenovnik['naziv']) ?></h2>
        <div class="actions">
            <a class="btn btn-light" href="index.php?page=cenovnici">← Svi cenovnici</a>
            <a class="btn btn-primary" href="index.php?page=cenovnik_stavke&cenovnik_id=<?= $id ?>">Uredi stavke</a>
        </div>
    </div>

    <p class="muted">Izvođač: <strong><?= e($cenovnik['izvodjac_naziv']) ?></strong></p>

    <form method="post" class="form-grid">
        <input type="hidden" name="snimi_cenovnik" value="1">

        <div>
            <label>Naziv</label>
            <input type="text" name="naziv" value="<?= e($cenovnik['naziv']) ?>" required>
        </div>

        <div>
            <label>Važi od</label>
            <input type="date" name="datum_od" value="<?= e($cenovnik['datum_od'] ?? '') ?>">
        </div>

        <div>
            <label>Važi do</label>
            <input type="date" name="datum_do" value="<?= e($cenovnik['datum_do'] ?? '') ?>">
        </div>

        <div>
            <button class="btn btn-primary" type="submit">Sačuvaj</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="toolbar">
        <h2>Stavke cenovnika</h2>
        <a class="btn btn-primary" href="index.php?page=cenovnik_stavke&cenovnik_id=<?= $id ?>">+ Dodaj stavke</a>
    </div>

    <?php if (!$stavke): ?>
        <div class="empty">Cenovnik još nema stavke.</div>
    <?php else: ?>
        <table class="table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left;">Element</th>
                    <th style="text-align:left;">Aktivnost</th>
                    <th style="text-align:right;">Cena</th>
                    <th style="text-align:center;">Jedinica</th>
                    <th style="text-align:center;">PDV</th>
                    <th style="text-align:left;">Napomena</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stavke as $s): ?>
                    <tr>
                        <td><?= e($s['element_naziv']) ?></td>
                        <td><?= e($s['aktivnost_naziv']) ?></td>
                        <td style="text-align:right;"><?= money_rs($s['cena']) ?></td>
                        <td style="text-align:center;"><?= e($s['jedinica_oznaka']) ?></td>
                        <td style="text-align:center;"><?= e($s['pdv']) ?>%</td>
                        <td><?= e($s['napomena'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
