<?php
$szId = get_int('sz_id');
$title = 'Ponude';
$subtitle = 'Opšte ponude, ponude za zgradu i ponude za konkretnu aktivnost.';

$izvodjacNazivCol = first_existing_column($conn, 'izvodjaci', ['naziv', 'ime', 'naziv_firme', 'firma'], 'naziv');

function jp_tip_ponude_label($tip) {
    $labels = [
        'opsta' => 'Opšta',
        'za_zgradu' => 'Za zgradu',
        'za_aktivnost' => 'Za aktivnost',
    ];
    return $labels[$tip] ?? $tip;
}

function jp_status_ponude_label($status) {
    $labels = [
        'na_cekanju' => 'Na čekanju',
        'prihvacena' => 'Prihvaćena',
        'odbijena' => 'Odbijena',
        'arhivirana' => 'Arhivirana',
        'obrisana' => 'Obrisana',
    ];
    return $labels[$status] ?? $status;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_ponudu'])) {
    $naziv = trim($_POST['naziv'] ?? '');
    $brojPonude = trim($_POST['broj_ponude'] ?? '');
    $tipPonude = $_POST['tip_ponude'] ?? 'opsta';
    $izvodjacId = (int)($_POST['izvodjac_id'] ?? 0);
    $cenovnikId = (int)($_POST['cenovnik_id'] ?? 0);
    $datumPonude = trim($_POST['datum_ponude'] ?? '');
    $vaziDo = trim($_POST['vazi_do'] ?? '');
    $opis = trim($_POST['opis'] ?? '');

    $dozvoljeniTipovi = ['opsta', 'za_zgradu', 'za_aktivnost'];
    if (!in_array($tipPonude, $dozvoljeniTipovi, true)) {
        $tipPonude = 'opsta';
    }

    $targetSzId = null;
    if ($tipPonude !== 'opsta') {
        $targetSzId = (int)($_POST['sz_id_ponude'] ?? $szId);
        if ($targetSzId <= 0) {
            redirect_to('index.php?page=ponude' . ($szId > 0 ? '&sz_id=' . $szId : '') . '&greska_zgrada=1');
        }
    }

    if ($naziv !== '' && $izvodjacId > 0) {
        $datumParam = $datumPonude !== '' ? $datumPonude : null;
        $vaziParam = $vaziDo !== '' ? $vaziDo : null;
        $cenovnikParam = $cenovnikId > 0 ? $cenovnikId : null;
        $status = 'na_cekanju';

        $stmt = $conn->prepare("INSERT INTO ponude
            (naziv, broj_ponude, tip_ponude, izvodjac_id, sz_id, cenovnik_id, datum_ponude, vazi_do, status_ponude, opis, aktivna)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param('sssiiissss', $naziv, $brojPonude, $tipPonude, $izvodjacId, $targetSzId, $cenovnikParam, $datumParam, $vaziParam, $status, $opis);
        $stmt->execute();
        $ponudaId = $conn->insert_id;

        redirect_to('index.php?page=ponuda&id=' . $ponudaId . '&dodato=1');
    }

    redirect_to('index.php?page=ponude' . ($szId > 0 ? '&sz_id=' . $szId : '') . '&greska=1');
}

if (isset($_GET['obrisi'])) {
    $id = (int)$_GET['obrisi'];
    $stmt = $conn->prepare("UPDATE ponude SET aktivna=0, status_ponude='obrisana' WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE ponuda_stavke SET aktivna=0 WHERE ponuda_id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    redirect_to('index.php?page=ponude' . ($szId > 0 ? '&sz_id=' . $szId : '') . '&obrisano=1');
}

$izvodjaci = db_all($conn, "SELECT id, `$izvodjacNazivCol` AS naziv FROM izvodjaci WHERE aktivan=1 ORDER BY `$izvodjacNazivCol`");
$zgrade = db_all($conn, "SELECT id, naziv FROM stambene_zajednice ORDER BY naziv");
$cenovnici = db_all($conn, "SELECT c.id, c.naziv, c.izvodjac_id, i.`$izvodjacNazivCol` AS izvodjac_naziv FROM cenovnici c JOIN izvodjaci i ON i.id=c.izvodjac_id WHERE c.aktivan=1 ORDER BY i.`$izvodjacNazivCol`, c.naziv");

$where = "p.aktivna=1";
$params = [];
$types = '';
if ($szId > 0) {
    $where .= " AND (p.sz_id IS NULL OR p.sz_id=0 OR p.sz_id=?)";
    $params[] = $szId;
    $types .= 'i';
}

$ponude = db_all(
    $conn,
    "SELECT p.*, i.`$izvodjacNazivCol` AS izvodjac_naziv, sz.naziv AS zgrada_naziv,
            COALESCE((SELECT SUM(ps.kolicina * ps.cena) FROM ponuda_stavke ps WHERE ps.ponuda_id=p.id AND ps.aktivna=1), 0) AS ukupno,
            COALESCE((SELECT COUNT(*) FROM ponuda_stavke ps WHERE ps.ponuda_id=p.id AND ps.aktivna=1), 0) AS broj_stavki
     FROM ponude p
     LEFT JOIN izvodjaci i ON i.id=p.izvodjac_id
     LEFT JOIN stambene_zajednice sz ON sz.id=p.sz_id
     WHERE $where
     ORDER BY p.datum_ponude DESC, p.id DESC",
    $types,
    $params
);

require __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['greska'])): ?><section class="card" style="border-left:4px solid red;">Popuni naziv i izvođača.</section><?php endif; ?>
<?php if (isset($_GET['greska_zgrada'])): ?><section class="card" style="border-left:4px solid red;">Za ponudu tipa „Za zgradu” ili „Za aktivnost” mora biti izabrana zgrada.</section><?php endif; ?>
<?php if (isset($_GET['obrisano'])): ?><section class="card" style="border-left:4px solid orange;">Ponuda je označena kao obrisana.</section><?php endif; ?>

<section class="card">
    <div class="toolbar">
        <h2>Nova ponuda</h2>
    </div>

    <form method="post" id="ponuda-form" style="display:grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap:16px; align-items:end;">
        <input type="hidden" name="dodaj_ponudu" value="1">

        <div style="grid-column: span 2;">
            <label>Naziv *</label>
            <input type="text" name="naziv" required placeholder="npr. Ponuda za servis lifta" style="width:100%;">
        </div>

        <div>
            <label>Broj ponude</label>
            <input type="text" name="broj_ponude" style="width:100%;">
        </div>

        <div>
            <label>Tip ponude</label>
            <select name="tip_ponude" id="tip_ponude" style="width:100%;">
                <option value="opsta">Opšta</option>
                <option value="za_zgradu" <?= $szId > 0 ? 'selected' : '' ?>>Za zgradu</option>
                <option value="za_aktivnost">Za aktivnost</option>
            </select>
        </div>

        <div id="zgrada-wrap">
            <label>Zgrada</label>
            <select name="sz_id_ponude" id="sz_id_ponude" style="width:100%;">
                <option value="">-- izaberi zgradu --</option>
                <?php foreach ($zgrade as $z): ?>
                    <option value="<?= (int)$z['id'] ?>" <?= ((int)$z['id'] === $szId) ? 'selected' : '' ?>><?= e($z['naziv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Izvođač *</label>
            <select name="izvodjac_id" required style="width:100%;">
                <option value="">-- izaberi izvođača --</option>
                <?php foreach ($izvodjaci as $i): ?>
                    <option value="<?= (int)$i['id'] ?>"><?= e($i['naziv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Cenovnik</label>
            <select name="cenovnik_id" style="width:100%;">
                <option value="">-- bez cenovnika --</option>
                <?php foreach ($cenovnici as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"><?= e($c['izvodjac_naziv'] . ' — ' . $c['naziv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Datum ponude</label>
            <input type="date" name="datum_ponude" value="<?= e(date('Y-m-d')) ?>" style="width:100%;">
        </div>

        <div>
            <label>Važi do</label>
            <input type="date" name="vazi_do" style="width:100%;">
        </div>

        <div style="grid-column:1 / -1;">
            <label>Napomena / opis</label>
            <textarea name="opis" rows="3" style="width:100%;"></textarea>
        </div>

        <div style="grid-column:1 / -1; display:flex; justify-content:flex-end;">
            <button class="btn btn-primary" type="submit">Dodaj ponudu</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="toolbar"><h2>Pregled ponuda</h2></div>
    <?php if (!$ponude): ?>
        <div class="empty">Nema evidentiranih ponuda.</div>
    <?php else: ?>
        <table class="table" style="width:100%; table-layout:fixed;">
            <thead>
                <tr>
                    <th style="width:28%; text-align:left;">Ponuda</th>
                    <th style="width:12%; text-align:left;">Tip</th>
                    <th style="width:18%; text-align:left;">Izvođač</th>
                    <th style="width:16%; text-align:left;">Zgrada</th>
                    <th style="width:10%; text-align:left;">Datum</th>
                    <th style="width:12%; text-align:right;">Vrednost</th>
                    <th style="width:12%; text-align:left;">Status</th>
                    <th style="width:18%; text-align:left;">Akcije</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ponude as $p): ?>
                <tr>
                    <td>
                        <strong><?= e($p['naziv']) ?></strong>
                        <?php if (!empty($p['broj_ponude'])): ?><br><span class="muted"><?= e($p['broj_ponude']) ?></span><?php endif; ?>
                    </td>
                    <td><?= e(jp_tip_ponude_label($p['tip_ponude'] ?? 'opsta')) ?></td>
                    <td><?= e($p['izvodjac_naziv'] ?? '') ?></td>
                    <td><?= e(!empty($p['zgrada_naziv']) ? $p['zgrada_naziv'] : 'Opšta') ?></td>
                    <td><?= e($p['datum_ponude'] ?? '') ?></td>
                    <td style="text-align:right;"><?= money_rs($p['ukupno'] ?? 0) ?></td>
                    <td><?= e(jp_status_ponude_label($p['status_ponude'] ?? 'na_cekanju')) ?></td>
                    <td class="actions" style="white-space:nowrap;">
                        <a class="btn btn-light btn-sm" href="index.php?page=ponuda&id=<?= (int)$p['id'] ?>">Otvori</a>
                        <a class="btn btn-primary btn-sm" href="index.php?page=ponuda_stavke&ponuda_id=<?= (int)$p['id'] ?>">Stavke</a>
                        <a class="btn btn-danger btn-sm" href="index.php?page=ponude<?= $szId > 0 ? '&sz_id=' . $szId : '' ?>&obrisi=<?= (int)$p['id'] ?>" onclick="return confirm('Označiti ponudu kao obrisanu? Stavke ponude se takođe neće prikazivati.');">Obriši</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<script>
(function() {
    const tip = document.getElementById('tip_ponude');
    const wrap = document.getElementById('zgrada-wrap');
    const zgrada = document.getElementById('sz_id_ponude');
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
