<?php
$id = get_int('id');
$szId = get_int('sz_id');
$szQuery = $szId > 0 ? '&sz_id=' . $szId : '';

if ($id <= 0) {
    die('Nije izabran izvođač.');
}

$izvodjac = db_one(
    $conn,
    "SELECT i.*, vs.naziv AS vrsta_subjekta
     FROM izvodjaci i
     LEFT JOIN vrste_subjekata vs ON vs.id = i.vrsta_subjekta_id
     WHERE i.id=? AND i.aktivan=1",
    'i',
    [$id]
);

if (!$izvodjac) {
    die('Izvođač nije pronađen ili je obrisan.');
}

$delatnosti = db_all(
    $conn,
    "SELECT d.*
     FROM izvodjac_delatnosti idel
     JOIN delatnosti d ON d.id = idel.delatnost_id
     WHERE idel.izvodjac_id=? AND d.aktivna=1
     ORDER BY d.naziv",
    'i',
    [$id]
);

$racuni = db_all($conn, "SELECT * FROM racuni_izvodjaca WHERE izvodjac_id=? AND aktivan=1 ORDER BY primarni DESC, id ASC", 'i', [$id]);
$cenovnici = db_all($conn, "SELECT * FROM cenovnici WHERE izvodjac_id=? AND aktivan=1 ORDER BY datum_od DESC, naziv", 'i', [$id]);
$ponude = [];
if (function_exists('table_exists') && table_exists($conn, 'ponude')) {
    $ponude = db_all($conn, "SELECT * FROM ponude WHERE izvodjac_id=? AND aktivna=1 ORDER BY datum_ponude DESC, naziv", 'i', [$id]);
}

$title = 'Izvođač: ' . ($izvodjac['naziv'] ?? '');
$subtitle = 'Pregled kartice izvođača bez uređivanja.';

require __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['snimljeno'])): ?>
    <section class="card" style="border-left:4px solid green;">Izmene su sačuvane.</section>
<?php endif; ?>

<section class="card">
    <div class="toolbar">
        <h2><?= e($izvodjac['naziv']) ?></h2>
        <div class="actions">
            <a class="btn btn-light" href="index.php?page=izvodjaci<?= $szQuery ?>">← Svi izvođači</a>
            <a class="btn btn-primary" href="index.php?page=izvodjac_uredi&id=<?= $id ?><?= $szQuery ?>">Uredi</a>
        </div>
    </div>

    <table class="table" style="width:100%; border-collapse:collapse;">
        <tbody>
            <tr><th style="text-align:left; width:220px;">Vrsta subjekta</th><td><?= e($izvodjac['vrsta_subjekta'] ?? '') ?></td></tr>
            <tr><th style="text-align:left;">PIB</th><td><?= e($izvodjac['pib'] ?? '') ?></td></tr>
            <tr><th style="text-align:left;">Matični broj</th><td><?= e($izvodjac['maticni_broj'] ?? '') ?></td></tr>
            <tr><th style="text-align:left;">Adresa</th><td><?= e($izvodjac['adresa'] ?? '') ?></td></tr>
            <tr><th style="text-align:left;">Kontakt osoba</th><td><?= e($izvodjac['kontakt_osoba'] ?? '') ?></td></tr>
            <tr><th style="text-align:left;">Telefon</th><td><?= e($izvodjac['telefon'] ?? '') ?></td></tr>
            <tr><th style="text-align:left;">Mobilni</th><td><?= e($izvodjac['mobilni'] ?? '') ?></td></tr>
            <tr><th style="text-align:left;">E-mail</th><td><?php if (!empty($izvodjac['email'])): ?><a href="mailto:<?= e($izvodjac['email']) ?>"><?= e($izvodjac['email']) ?></a><?php endif; ?></td></tr>
            <tr><th style="text-align:left;">Web</th><td><?php if (!empty($izvodjac['web'])): ?><a href="<?= e($izvodjac['web']) ?>" target="_blank"><?= e($izvodjac['web']) ?></a><?php endif; ?></td></tr>
            <tr><th style="text-align:left;">Ocena</th><td><?= (int)($izvodjac['ocena'] ?? 0) > 0 ? str_repeat('★', (int)$izvodjac['ocena']) : 'Bez ocene' ?></td></tr>
            <tr><th style="text-align:left;">Napomena</th><td><?= nl2br(e($izvodjac['napomena'] ?? '')) ?></td></tr>
        </tbody>
    </table>
</section>

<section class="card">
    <h2>Delatnosti</h2>

    <?php if (!$delatnosti): ?>
        <div class="empty">Nisu označene delatnosti.</div>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($delatnosti as $d): ?>
                <div class="card card-muted" style="padding:10px;">
                    <?= e(($d['ikonica'] ? $d['ikonica'] . ' ' : '') . $d['naziv']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Tekući računi</h2>

    <?php if (!$racuni): ?>
        <div class="empty">Nema unetih tekućih računa.</div>
    <?php else: ?>
        <table class="table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left;">Banka</th>
                    <th style="text-align:left;">Broj računa</th>
                    <th style="text-align:center; width:100px;">Valuta</th>
                    <th style="text-align:center; width:120px;">Primarni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($racuni as $r): ?>
                    <tr>
                        <td><?= e($r['banka'] ?? '') ?></td>
                        <td><strong><?= e($r['broj_racuna'] ?? '') ?></strong></td>
                        <td style="text-align:center;"><?= e($r['valuta'] ?? 'RSD') ?></td>
                        <td style="text-align:center;"><?= (int)($r['primarni'] ?? 0) === 1 ? '✔' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card">
    <div class="toolbar">
        <h2>Cenovnici</h2>
        <a class="btn btn-primary" href="index.php?page=cenovnici&izvodjac_id=<?= $id ?><?= $szQuery ?>">Otvori cenovnike</a>
    </div>

    <?php if (!$cenovnici): ?>
        <div class="empty">Za ovog izvođača još nema unetih cenovnika.</div>
    <?php else: ?>
        <table class="table" style="width:100%; border-collapse:collapse;">
            <thead><tr><th style="text-align:left;">Naziv</th><th>Važi od</th><th>Važi do</th><th style="text-align:right;">Akcija</th></tr></thead>
            <tbody>
            <?php foreach ($cenovnici as $c): ?>
                <tr>
                    <td><?= e($c['naziv']) ?></td>
                    <td><?= e($c['datum_od'] ?? '') ?></td>
                    <td><?= e($c['datum_do'] ?? '') ?></td>
                    <td style="text-align:right;"><a class="btn btn-light btn-sm" href="index.php?page=cenovnik&id=<?= (int)$c['id'] ?><?= $szQuery ?>">Otvori</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php if ($ponude): ?>
<section class="card">
    <h2>Ponude</h2>
    <table class="table" style="width:100%; border-collapse:collapse;">
        <thead><tr><th style="text-align:left;">Naziv</th><th>Datum</th><th>Važi do</th><th style="text-align:right;">Iznos</th></tr></thead>
        <tbody>
        <?php foreach ($ponude as $p): ?>
            <tr>
                <td><?= e($p['naziv']) ?></td>
                <td><?= e($p['datum_ponude'] ?? '') ?></td>
                <td><?= e($p['vazi_do'] ?? '') ?></td>
                <td style="text-align:right;"><?= isset($p['iznos']) ? money_rs($p['iznos']) : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
