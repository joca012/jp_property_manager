<?php
$id = get_int('id');
$title = 'Ponuda';
$subtitle = 'Zaglavlje ponude, status i ukupna vrednost.';
if ($id <= 0) { die('Nije izabrana ponuda.'); }

$izvodjacNazivCol = first_existing_column($conn, 'izvodjaci', ['naziv', 'ime', 'naziv_firme', 'firma'], 'naziv');

function jp_tip_ponude_label_single($tip) {
    $labels = ['opsta'=>'Opšta','za_zgradu'=>'Za zgradu','za_aktivnost'=>'Za aktivnost'];
    return $labels[$tip] ?? $tip;
}

function jp_status_ponude_label_single($status) {
    $labels = ['na_cekanju'=>'Na čekanju','prihvacena'=>'Prihvaćena','odbijena'=>'Odbijena','arhivirana'=>'Arhivirana','obrisana'=>'Obrisana'];
    return $labels[$status] ?? $status;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['snimi_ponudu'])) {
    $naziv = trim($_POST['naziv'] ?? '');
    $brojPonude = trim($_POST['broj_ponude'] ?? '');
    $tipPonude = $_POST['tip_ponude'] ?? 'opsta';
    $szIdPost = (int)($_POST['sz_id'] ?? 0);
    $datumPonude = trim($_POST['datum_ponude'] ?? '');
    $vaziDo = trim($_POST['vazi_do'] ?? '');
    $status = $_POST['status_ponude'] ?? 'na_cekanju';
    $opis = trim($_POST['opis'] ?? '');
    $cenovnikId = (int)($_POST['cenovnik_id'] ?? 0);

    $dozvoljeniTipovi = ['opsta', 'za_zgradu', 'za_aktivnost'];
    if (!in_array($tipPonude, $dozvoljeniTipovi, true)) {
        $tipPonude = 'opsta';
    }

    $dozvoljeniStatusi = ['na_cekanju', 'prihvacena', 'odbijena', 'arhivirana'];
    if (!in_array($status, $dozvoljeniStatusi, true)) {
        $status = 'na_cekanju';
    }

    $szParam = null;
    if ($tipPonude !== 'opsta') {
        if ($szIdPost <= 0) {
            redirect_to('index.php?page=ponuda&id=' . $id . '&greska_zgrada=1');
        }
        $szParam = $szIdPost;
    }

    if ($naziv !== '') {
        $datumParam = $datumPonude !== '' ? $datumPonude : null;
        $vaziParam = $vaziDo !== '' ? $vaziDo : null;
        $cenovnikParam = $cenovnikId > 0 ? $cenovnikId : null;

        $stmt = $conn->prepare("UPDATE ponude SET naziv=?, broj_ponude=?, tip_ponude=?, sz_id=?, cenovnik_id=?, datum_ponude=?, vazi_do=?, status_ponude=?, opis=? WHERE id=?");
        $stmt->bind_param('sssiissssi', $naziv, $brojPonude, $tipPonude, $szParam, $cenovnikParam, $datumParam, $vaziParam, $status, $opis, $id);
        $stmt->execute();
        redirect_to('index.php?page=ponuda&id=' . $id . '&snimljeno=1');
    }

    redirect_to('index.php?page=ponuda&id=' . $id . '&greska=1');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uvezi_cenovnik'])) {
    $cenovnikId = (int)($_POST['cenovnik_id_import'] ?? 0);
    if ($cenovnikId > 0) {
        $stavke = db_all($conn, "SELECT * FROM cenovnik_stavke WHERE cenovnik_id=? AND aktivna=1", 'i', [$cenovnikId]);
        foreach ($stavke as $s) {
            $stmt = $conn->prepare("INSERT INTO ponuda_stavke (ponuda_id, cenovnik_stavka_id, element_id, aktivnost_id, jedinica_id, naziv, kolicina, cena, napomena, aktivna) VALUES (?, ?, ?, ?, ?, '', 1, ?, ?, 1)");
            $stmt->bind_param('iiiiids', $id, $s['id'], $s['element_id'], $s['aktivnost_id'], $s['jedinica_id'], $s['cena'], $s['napomena']);
            $stmt->execute();
        }
        $stmt = $conn->prepare("UPDATE ponude SET cenovnik_id=? WHERE id=?");
        $stmt->bind_param('ii', $cenovnikId, $id);
        $stmt->execute();
        redirect_to('index.php?page=ponuda&id=' . $id . '&uvezeno=1');
    }
}

$ponuda = db_one($conn, "SELECT p.*, i.`$izvodjacNazivCol` AS izvodjac_naziv, sz.naziv AS zgrada_naziv FROM ponude p LEFT JOIN izvodjaci i ON i.id=p.izvodjac_id LEFT JOIN stambene_zajednice sz ON sz.id=p.sz_id WHERE p.id=?", 'i', [$id]);
if (!$ponuda) { die('Ponuda nije pronađena.'); }

$zgrade = db_all($conn, "SELECT id, naziv FROM stambene_zajednice ORDER BY naziv");
$cenovnici = db_all($conn, "SELECT c.id, c.naziv, i.`$izvodjacNazivCol` AS izvodjac_naziv FROM cenovnici c JOIN izvodjaci i ON i.id=c.izvodjac_id WHERE c.aktivan=1 AND c.izvodjac_id=? ORDER BY c.datum_od DESC, c.naziv", 'i', [(int)$ponuda['izvodjac_id']]);
$stavke = db_all($conn, "SELECT ps.*, se.naziv AS element_naziv, sa.naziv AS aktivnost_naziv, oj.oznaka AS jedinica FROM ponuda_stavke ps LEFT JOIN sifarnik_elemenata se ON se.id=ps.element_id LEFT JOIN sifarnik_aktivnosti sa ON sa.id=ps.aktivnost_id LEFT JOIN obracunske_jedinice oj ON oj.id=ps.jedinica_id WHERE ps.ponuda_id=? AND ps.aktivna=1 ORDER BY se.naziv, sa.naziv", 'i', [$id]);
$ukupno = 0;
foreach ($stavke as $s) { $ukupno += (float)$s['kolicina'] * (float)$s['cena']; }

require __DIR__ . '/../includes/header.php';
?>
<?php if (isset($_GET['snimljeno'])): ?><section class="card" style="border-left:4px solid green;">Ponuda je sačuvana.</section><?php endif; ?>
<?php if (isset($_GET['dodato'])): ?><section class="card" style="border-left:4px solid green;">Ponuda je dodata.</section><?php endif; ?>
<?php if (isset($_GET['uvezeno'])): ?><section class="card" style="border-left:4px solid green;">Stavke iz cenovnika su uvezene.</section><?php endif; ?>
<?php if (isset($_GET['greska'])): ?><section class="card" style="border-left:4px solid red;">Naziv ponude je obavezan.</section><?php endif; ?>
<?php if (isset($_GET['greska_zgrada'])): ?><section class="card" style="border-left:4px solid red;">Za ovaj tip ponude mora biti izabrana zgrada.</section><?php endif; ?>

<section class="card">
    <div class="toolbar">
        <h2><?= e($ponuda['naziv']) ?></h2>
        <div class="actions">
            <a class="btn btn-light" href="index.php?page=ponude<?= !empty($ponuda['sz_id']) ? '&sz_id=' . (int)$ponuda['sz_id'] : '' ?>">← Ponude</a>
            <a class="btn btn-primary" href="index.php?page=ponuda_stavke&ponuda_id=<?= $id ?>">Uredi stavke</a>
        </div>
    </div>

    <p class="muted">
        Izvođač: <strong><?= e($ponuda['izvodjac_naziv']) ?></strong>
        · Tip: <strong><?= e(jp_tip_ponude_label_single($ponuda['tip_ponude'] ?? 'opsta')) ?></strong>
        · Zgrada: <strong><?= e(!empty($ponuda['zgrada_naziv']) ? $ponuda['zgrada_naziv'] : 'Opšta') ?></strong>
        · Ukupno: <strong><?= money_rs($ukupno) ?></strong>
    </p>

    <form method="post" id="ponuda-edit-form" style="display:grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap:16px; align-items:end;">
        <input type="hidden" name="snimi_ponudu" value="1">

        <div style="grid-column: span 2;">
            <label>Naziv</label>
            <input type="text" name="naziv" value="<?= e($ponuda['naziv']) ?>" required style="width:100%;">
        </div>

        <div>
            <label>Broj ponude</label>
            <input type="text" name="broj_ponude" value="<?= e($ponuda['broj_ponude'] ?? '') ?>" style="width:100%;">
        </div>

        <div>
            <label>Tip ponude</label>
            <select name="tip_ponude" id="tip_ponude_edit" style="width:100%;">
                <option value="opsta" <?= ($ponuda['tip_ponude'] ?? '') === 'opsta' ? 'selected' : '' ?>>Opšta</option>
                <option value="za_zgradu" <?= ($ponuda['tip_ponude'] ?? '') === 'za_zgradu' ? 'selected' : '' ?>>Za zgradu</option>
                <option value="za_aktivnost" <?= ($ponuda['tip_ponude'] ?? '') === 'za_aktivnost' ? 'selected' : '' ?>>Za aktivnost</option>
            </select>
        </div>

        <div id="zgrada-wrap-edit">
            <label>Zgrada</label>
            <select name="sz_id" id="sz_id_edit" style="width:100%;">
                <option value="">-- izaberi zgradu --</option>
                <?php foreach ($zgrade as $z): ?>
                    <option value="<?= (int)$z['id'] ?>" <?= ((int)$z['id'] === (int)($ponuda['sz_id'] ?? 0)) ? 'selected' : '' ?>><?= e($z['naziv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Cenovnik</label>
            <select name="cenovnik_id" style="width:100%;">
                <option value="">-- bez cenovnika --</option>
                <?php foreach ($cenovnici as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === (int)($ponuda['cenovnik_id'] ?? 0)) ? 'selected' : '' ?>><?= e($c['naziv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Status</label>
            <select name="status_ponude" style="width:100%;">
                <option value="na_cekanju" <?= ($ponuda['status_ponude'] ?? '') === 'na_cekanju' ? 'selected' : '' ?>>Na čekanju</option>
                <option value="prihvacena" <?= ($ponuda['status_ponude'] ?? '') === 'prihvacena' ? 'selected' : '' ?>>Prihvaćena</option>
                <option value="odbijena" <?= ($ponuda['status_ponude'] ?? '') === 'odbijena' ? 'selected' : '' ?>>Odbijena</option>
                <option value="arhivirana" <?= ($ponuda['status_ponude'] ?? '') === 'arhivirana' ? 'selected' : '' ?>>Arhivirana</option>
            </select>
        </div>

        <div>
            <label>Datum</label>
            <input type="date" name="datum_ponude" value="<?= e($ponuda['datum_ponude'] ?? '') ?>" style="width:100%;">
        </div>

        <div>
            <label>Važi do</label>
            <input type="date" name="vazi_do" value="<?= e($ponuda['vazi_do'] ?? '') ?>" style="width:100%;">
        </div>

        <div style="grid-column:1 / -1;">
            <label>Napomena</label>
            <textarea name="opis" rows="3" style="width:100%;"><?= e($ponuda['opis'] ?? '') ?></textarea>
        </div>

        <div style="grid-column:1 / -1; display:flex; justify-content:flex-end;">
            <button class="btn btn-primary" type="submit">Sačuvaj ponudu</button>
        </div>
    </form>
</section>

<?php if ($cenovnici): ?>
<section class="card">
    <div class="toolbar"><h2>Uvoz stavki iz cenovnika</h2></div>
    <form method="post" style="display:grid; grid-template-columns: minmax(280px, 1fr) auto; gap:16px; align-items:end;">
        <input type="hidden" name="uvezi_cenovnik" value="1">
        <div>
            <label>Cenovnik izvođača</label>
            <select name="cenovnik_id_import" required style="width:100%;">
                <?php foreach ($cenovnici as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= e($c['naziv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button class="btn btn-primary" type="submit" onclick="return confirm('Uvesti stavke iz izabranog cenovnika?');">Uvezi stavke</button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="card">
    <div class="toolbar">
        <h2>Stavke ponude</h2>
        <a class="btn btn-primary" href="index.php?page=ponuda_stavke&ponuda_id=<?= $id ?>">Uredi stavke</a>
    </div>
    <?php if (!$stavke): ?>
        <div class="empty">Ponuda nema stavke.</div>
    <?php else: ?>
        <table class="table" style="width:100%; table-layout:fixed;">
            <thead>
                <tr>
                    <th style="text-align:left; width:24%;">Element</th>
                    <th style="text-align:left; width:24%;">Aktivnost</th>
                    <th style="text-align:left; width:8%;">JM</th>
                    <th style="text-align:right; width:10%;">Količina</th>
                    <th style="text-align:right; width:14%;">Cena</th>
                    <th style="text-align:right; width:14%;">Ukupno</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stavke as $s): $sum = (float)$s['kolicina'] * (float)$s['cena']; ?>
                <tr>
                    <td><?= e($s['element_naziv'] ?? '') ?></td>
                    <td><?= e($s['aktivnost_naziv'] ?? ($s['naziv'] ?? '')) ?></td>
                    <td><?= e($s['jedinica'] ?? '') ?></td>
                    <td style="text-align:right;"><?= e($s['kolicina']) ?></td>
                    <td style="text-align:right;"><?= money_rs($s['cena']) ?></td>
                    <td style="text-align:right;"><?= money_rs($sum) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="text-align:right;"><strong>Ukupno: <?= money_rs($ukupno) ?></strong></p>
    <?php endif; ?>
</section>

<script>
(function() {
    const tip = document.getElementById('tip_ponude_edit');
    const wrap = document.getElementById('zgrada-wrap-edit');
    const zgrada = document.getElementById('sz_id_edit');
    if (!tip || !wrap || !zgrada) return;

    function refresh() {
        if (tip.value === 'opsta') {
            wrap.style.opacity = '0.45';
            zgrada.value = '';
            zgrada.disabled = true;
            zgrada.required = false;
        } else {
            wrap.style.opacity = '1';
            zgrada.disabled = false;
            zgrada.required = true;
        }
    }
    tip.addEventListener('change', refresh);
    refresh();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
