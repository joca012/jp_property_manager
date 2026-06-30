<?php
$id = get_int('id');
$szId = get_int('sz_id');
$szQuery = $szId > 0 ? '&sz_id=' . $szId : '';

if ($id <= 0) {
    die('Nije izabran izvođač.');
}

$izvodjac = db_one($conn, "SELECT * FROM izvodjaci WHERE id=? AND aktivan=1", 'i', [$id]);
if (!$izvodjac) {
    die('Izvođač nije pronađen ili je obrisan.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['snimi_izvodjaca'])) {
    $naziv = trim($_POST['naziv'] ?? '');
    $vrstaSubjektaId = (int)($_POST['vrsta_subjekta_id'] ?? 0);
    $pib = trim($_POST['pib'] ?? '');
    $maticniBroj = trim($_POST['maticni_broj'] ?? '');
    $adresa = trim($_POST['adresa'] ?? '');
    $kontaktOsoba = trim($_POST['kontakt_osoba'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $mobilni = trim($_POST['mobilni'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $web = trim($_POST['web'] ?? '');
    $napomena = trim($_POST['napomena'] ?? '');
    $ocena = (int)($_POST['ocena'] ?? 0);
    $delatnosti = $_POST['delatnosti'] ?? [];

    if ($ocena < 0 || $ocena > 5) { $ocena = 0; }

    if ($naziv !== '') {
        $kategorijaLegacy = '';
        if ($delatnosti) {
            $ids = array_map('intval', $delatnosti);
            $ids = array_filter($ids, fn($v) => $v > 0);
            if ($ids) {
                $in = implode(',', $ids);
                $rows = db_all($conn, "SELECT naziv FROM delatnosti WHERE id IN ($in) ORDER BY naziv");
                $names = array_map(fn($r) => $r['naziv'], $rows);
                $kategorijaLegacy = implode(', ', $names);
            }
        }

        $stmt = $conn->prepare("UPDATE izvodjaci SET
            naziv=?, vrsta_subjekta_id=?, kategorija=?, pib=?, maticni_broj=?, adresa=?, kontakt_osoba=?, telefon=?, mobilni=?, email=?, web=?, napomena=?, ocena=?
            WHERE id=?");
        $stmt->bind_param(
            'sissssssssssii',
            $naziv,
            $vrstaSubjektaId,
            $kategorijaLegacy,
            $pib,
            $maticniBroj,
            $adresa,
            $kontaktOsoba,
            $telefon,
            $mobilni,
            $email,
            $web,
            $napomena,
            $ocena,
            $id
        );
        $stmt->execute();

        $stmtDel = $conn->prepare("DELETE FROM izvodjac_delatnosti WHERE izvodjac_id=?");
        $stmtDel->bind_param('i', $id);
        $stmtDel->execute();

        foreach ($delatnosti as $delatnostId) {
            $delatnostId = (int)$delatnostId;
            if ($delatnostId > 0) {
                $stmtD = $conn->prepare("INSERT INTO izvodjac_delatnosti (izvodjac_id, delatnost_id) VALUES (?, ?)");
                $stmtD->bind_param('ii', $id, $delatnostId);
                $stmtD->execute();
            }
        }

        /* RAČUNI — sve izmene se čuvaju istim dugmetom */
        $racunBanka = $_POST['racun_banka'] ?? [];
        $racunBroj = $_POST['racun_broj'] ?? [];
        $racunValuta = $_POST['racun_valuta'] ?? [];
        $racunObrisi = $_POST['racun_obrisi'] ?? [];
        $primarniRacunId = (int)($_POST['primarni_racun_id'] ?? 0);
        $noviPrimarni = isset($_POST['novi_racun_primarni']);

        $postojećiRacuni = db_all($conn, "SELECT id FROM racuni_izvodjaca WHERE izvodjac_id=? AND aktivan=1", 'i', [$id]);

        foreach ($postojećiRacuni as $r) {
            $rid = (int)$r['id'];
            $banka = trim($racunBanka[$rid] ?? '');
            $broj = trim($racunBroj[$rid] ?? '');
            $valuta = trim($racunValuta[$rid] ?? 'RSD');
            if ($valuta === '') { $valuta = 'RSD'; }

            $obrisi = isset($racunObrisi[$rid]) || $broj === '';

            if ($obrisi) {
                $stmtR = $conn->prepare("UPDATE racuni_izvodjaca SET aktivan=0, primarni=0 WHERE id=? AND izvodjac_id=?");
                $stmtR->bind_param('ii', $rid, $id);
                $stmtR->execute();
            } else {
                $primarni = ($primarniRacunId === $rid && !$noviPrimarni) ? 1 : 0;
                $stmtR = $conn->prepare("
                    UPDATE racuni_izvodjaca
                    SET banka=?, broj_racuna=?, valuta=?, primarni=?
                    WHERE id=? AND izvodjac_id=?
                ");
                $stmtR->bind_param('sssiii', $banka, $broj, $valuta, $primarni, $rid, $id);
                $stmtR->execute();
            }
        }

        $novaBanka = trim($_POST['novi_racun_banka'] ?? '');
        $noviBroj = trim($_POST['novi_racun_broj'] ?? '');
        $novaValuta = trim($_POST['novi_racun_valuta'] ?? 'RSD');
        if ($novaValuta === '') { $novaValuta = 'RSD'; }

        if ($noviBroj !== '') {
            if ($noviPrimarni) {
                $stmt0 = $conn->prepare("UPDATE racuni_izvodjaca SET primarni=0 WHERE izvodjac_id=?");
                $stmt0->bind_param('i', $id);
                $stmt0->execute();
            }

            $primarni = $noviPrimarni ? 1 : 0;
            $stmtN = $conn->prepare("
                INSERT INTO racuni_izvodjaca
                (izvodjac_id, banka, broj_racuna, valuta, primarni, aktivan)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmtN->bind_param('isssi', $id, $novaBanka, $noviBroj, $novaValuta, $primarni);
            $stmtN->execute();
        }

        $aktivniRacuni = db_all($conn, "SELECT id, primarni FROM racuni_izvodjaca WHERE izvodjac_id=? AND aktivan=1 ORDER BY primarni DESC, id ASC", 'i', [$id]);
        $imaPrimarni = false;
        foreach ($aktivniRacuni as $r) {
            if ((int)$r['primarni'] === 1) { $imaPrimarni = true; break; }
        }
        if (!$imaPrimarni && $aktivniRacuni) {
            $prviId = (int)$aktivniRacuni[0]['id'];
            $stmtP = $conn->prepare("UPDATE racuni_izvodjaca SET primarni=1 WHERE id=? AND izvodjac_id=?");
            $stmtP->bind_param('ii', $prviId, $id);
            $stmtP->execute();
        }

        redirect_to('index.php?page=izvodjac&id=' . $id . $szQuery . '&snimljeno=1');
    }
}

$izvodjac = db_one(
    $conn,
    "SELECT i.*, vs.naziv AS vrsta_subjekta
     FROM izvodjaci i
     LEFT JOIN vrste_subjekata vs ON vs.id = i.vrsta_subjekta_id
     WHERE i.id=?",
    'i',
    [$id]
);

$vrste = db_all($conn, "SELECT * FROM vrste_subjekata WHERE aktivna=1 ORDER BY naziv");
$delatnosti = db_all($conn, "SELECT * FROM delatnosti WHERE aktivna=1 ORDER BY naziv");
$izabraneRows = db_all($conn, "SELECT delatnost_id FROM izvodjac_delatnosti WHERE izvodjac_id=?", 'i', [$id]);
$izabrane = array_map(fn($r) => (int)$r['delatnost_id'], $izabraneRows);
$racuni = db_all($conn, "SELECT * FROM racuni_izvodjaca WHERE izvodjac_id=? AND aktivan=1 ORDER BY primarni DESC, id ASC", 'i', [$id]);

$title = 'Uredi izvođača: ' . ($izvodjac['naziv'] ?? '');
$subtitle = 'Sve izmene na ovoj strani čuvaju se jednim dugmetom na dnu.';

require __DIR__ . '/../includes/header.php';
?>

<style>
.jp-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px 28px; align-items:start; }
.jp-field label { display:block; margin-bottom:6px; font-weight:600; }
.jp-field input, .jp-field select, .jp-field textarea { width:100%; box-sizing:border-box; }
.jp-field-full { grid-column:1 / -1; }
.jp-check-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; }
.jp-check-card { display:flex; gap:8px; align-items:center; padding:10px; min-height:44px; }
@media (max-width: 900px) { .jp-form-grid, .jp-check-grid { grid-template-columns:1fr; } }
</style>


<?php if (isset($_GET['dodato'])): ?>
    <section class="card" style="border-left:4px solid green;">Izvođač je dodat. Dopuni podatke i sačuvaj izmene.</section>
<?php endif; ?>

<form method="post" id="izvodjac-form">
    <input type="hidden" name="snimi_izvodjaca" value="1">

    <section class="card">
        <div class="toolbar">
            <h2>Opšti podaci</h2>
            <div class="actions">
                <a class="btn btn-light" href="index.php?page=izvodjac&id=<?= $id ?><?= $szQuery ?>">← Pregled</a>
                <a class="btn btn-light" href="index.php?page=izvodjaci<?= $szQuery ?>">Svi izvođači</a>
            </div>
        </div>

        <div class="jp-form-grid">
            <div class="jp-field jp-field-full">
                <label>Naziv *</label>
                <input type="text" name="naziv" required value="<?= e($izvodjac['naziv'] ?? '') ?>">
            </div>

            <div class="jp-field">
                <label>Vrsta subjekta</label>
                <select name="vrsta_subjekta_id">
                    <option value="0">-- izaberi --</option>
                    <?php foreach ($vrste as $v): ?>
                        <option value="<?= (int)$v['id'] ?>" <?= (int)($izvodjac['vrsta_subjekta_id'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>><?= e($v['naziv']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="jp-field"><label>PIB</label><input type="text" name="pib" value="<?= e($izvodjac['pib'] ?? '') ?>"></div>
            <div class="jp-field"><label>Matični broj</label><input type="text" name="maticni_broj" value="<?= e($izvodjac['maticni_broj'] ?? '') ?>"></div>
            <div class="jp-field"><label>Adresa</label><input type="text" name="adresa" value="<?= e($izvodjac['adresa'] ?? '') ?>"></div>
            <div class="jp-field"><label>Kontakt osoba</label><input type="text" name="kontakt_osoba" value="<?= e($izvodjac['kontakt_osoba'] ?? '') ?>"></div>
            <div class="jp-field"><label>Telefon</label><input type="text" name="telefon" value="<?= e($izvodjac['telefon'] ?? '') ?>"></div>
            <div class="jp-field"><label>Mobilni</label><input type="text" name="mobilni" value="<?= e($izvodjac['mobilni'] ?? '') ?>"></div>
            <div class="jp-field"><label>E-mail</label><input type="email" name="email" value="<?= e($izvodjac['email'] ?? '') ?>"></div>
            <div class="jp-field"><label>Web</label><input type="text" name="web" value="<?= e($izvodjac['web'] ?? '') ?>"></div>

            <div class="jp-field">
                <label>Ocena</label>
                <select name="ocena">
                    <option value="0">Bez ocene</option>
                    <?php for ($o=5; $o>=1; $o--): ?>
                        <option value="<?= $o ?>" <?= (int)($izvodjac['ocena'] ?? 0) === $o ? 'selected' : '' ?>><?= str_repeat('★', $o) ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="jp-field jp-field-full">
                <label>Napomena</label>
                <textarea name="napomena" rows="4"><?= e($izvodjac['napomena'] ?? '') ?></textarea>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Delatnosti</h2>

        <div class="jp-check-grid">
            <?php foreach ($delatnosti as $d): ?>
                <label class="card card-muted jp-check-card">
                    <input type="checkbox" name="delatnosti[]" value="<?= (int)$d['id'] ?>" <?= in_array((int)$d['id'], $izabrane, true) ? 'checked' : '' ?>>
                    <span><?= e(($d['ikonica'] ? $d['ikonica'] . ' ' : '') . $d['naziv']) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h2>Tekući računi</h2>

        <?php if (!$racuni): ?>
            <div class="empty">Nema unetih tekućih računa. Dodaj prvi račun ispod.</div>
        <?php else: ?>
            <table class="table" style="width:100%; border-collapse:collapse; table-layout:fixed;">
                <thead>
                    <tr>
                        <th style="text-align:left; width:24%;">Banka</th>
                        <th style="text-align:left; width:34%;">Broj računa</th>
                        <th style="text-align:center; width:90px;">Valuta</th>
                        <th style="text-align:center; width:110px;">Primarni</th>
                        <th style="text-align:center; width:110px;">Ukloni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($racuni as $r): ?>
                        <tr>
                            <td>
                                <input type="text" name="racun_banka[<?= (int)$r['id'] ?>]" value="<?= e($r['banka'] ?? '') ?>">
                            </td>
                            <td>
                                <input type="text" name="racun_broj[<?= (int)$r['id'] ?>]" value="<?= e($r['broj_racuna'] ?? '') ?>">
                            </td>
                            <td>
                                <input type="text" name="racun_valuta[<?= (int)$r['id'] ?>]" value="<?= e($r['valuta'] ?? 'RSD') ?>" style="text-align:center;">
                            </td>
                            <td style="text-align:center;">
                                <input type="radio" name="primarni_racun_id" value="<?= (int)$r['id'] ?>" <?= (int)($r['primarni'] ?? 0) === 1 ? 'checked' : '' ?>>
                            </td>
                            <td style="text-align:center;">
                                <input type="checkbox" name="racun_obrisi[<?= (int)$r['id'] ?>]" value="1">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3 style="margin-top:22px;">Dodaj novi račun</h3>

        <div class="jp-form-grid">
            <div class="jp-field"><label>Banka</label><input type="text" name="novi_racun_banka" placeholder="npr. Banca Intesa"></div>
            <div class="jp-field"><label>Broj računa</label><input type="text" name="novi_racun_broj" placeholder="npr. 160-0000000000000-00"></div>
            <div class="jp-field"><label>Valuta</label><input type="text" name="novi_racun_valuta" value="RSD"></div>
            <div style="padding-bottom:8px;">
                <label style="display:flex; gap:8px; align-items:center; margin:0;">
                    <input type="checkbox" name="novi_racun_primarni" value="1"> Primarni račun
                </label>
            </div>
        </div>
    </section>

    <section class="card" style="border-left:4px solid #2563eb;">
        <div style="display:flex; justify-content:space-between; gap:12px; align-items:center;">
            <div>
                <strong>Potvrda izmena</strong>
                <div class="muted">Ovo dugme čuva opšte podatke, delatnosti i tekuće račune.</div>
            </div>
            <button class="btn btn-primary" type="submit">Sačuvaj sve izmene</button>
        </div>
    </section>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
