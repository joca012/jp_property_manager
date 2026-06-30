<?php
$ponudaId = get_int('ponuda_id');
$editId = get_int('edit_id');

$title = 'Stavke ponude';
$subtitle = 'Stavke ponude bez PDV obračuna; sve cene su bruto FCO.';

if ($ponudaId <= 0) {
    die('Nije izabrana ponuda.');
}

$ponuda = db_one($conn, "SELECT * FROM ponude WHERE id=?", 'i', [$ponudaId]);
if (!$ponuda) {
    die('Ponuda nije pronađena.');
}

/* =========================
   DODAVANJE STAVKE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_stavku'])) {
    $elementId = (int)($_POST['element_id'] ?? 0);
    $aktivnostId = (int)($_POST['aktivnost_id'] ?? 0);
    $jedinicaId = (int)($_POST['jedinica_id'] ?? 0);
    $kolicinaRaw = trim((string)($_POST['kolicina'] ?? ''));
    $kolicina = ($kolicinaRaw === '') ? 0 : numeric_value($kolicinaRaw);
    $cena = numeric_value($_POST['cena'] ?? 0);
    $napomena = trim($_POST['napomena'] ?? '');

    // Količina 0 znači: ne obračunava se ovde, već kasnije iz zgrade/programa
    // npr. po stanu, po liftu, po PP aparatu, po hidrantima...
    if ($kolicina < 0) {
        $kolicina = 0;
    }

    if ($elementId > 0 && $aktivnostId > 0 && $jedinicaId > 0) {
        $stmt = $conn->prepare("INSERT INTO ponuda_stavke
            (ponuda_id, element_id, aktivnost_id, jedinica_id, naziv, kolicina, cena, napomena, aktivna)
            VALUES (?, ?, ?, ?, '', ?, ?, ?, 1)");
        $stmt->bind_param('iiiidds', $ponudaId, $elementId, $aktivnostId, $jedinicaId, $kolicina, $cena, $napomena);
        $stmt->execute();

        redirect_to('index.php?page=ponuda_stavke&ponuda_id=' . $ponudaId . '&dodato=1');
    }

    redirect_to('index.php?page=ponuda_stavke&ponuda_id=' . $ponudaId . '&greska=1');
}

/* =========================
   IZMENA STAVKE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['izmeni_stavku'])) {
    $stavkaId = (int)($_POST['stavka_id'] ?? 0);
    $elementId = (int)($_POST['element_id'] ?? 0);
    $aktivnostId = (int)($_POST['aktivnost_id'] ?? 0);
    $jedinicaId = (int)($_POST['jedinica_id'] ?? 0);
    $kolicinaRaw = trim((string)($_POST['kolicina'] ?? ''));
    $kolicina = ($kolicinaRaw === '') ? 0 : numeric_value($kolicinaRaw);
    $cena = numeric_value($_POST['cena'] ?? 0);
    $napomena = trim($_POST['napomena'] ?? '');

    if ($kolicina < 0) {
        $kolicina = 0;
    }

    if ($stavkaId > 0 && $elementId > 0 && $aktivnostId > 0 && $jedinicaId > 0) {
        $stmt = $conn->prepare("UPDATE ponuda_stavke
            SET element_id=?, aktivnost_id=?, jedinica_id=?, kolicina=?, cena=?, napomena=?
            WHERE id=? AND ponuda_id=?");
        $stmt->bind_param('iiiddsii', $elementId, $aktivnostId, $jedinicaId, $kolicina, $cena, $napomena, $stavkaId, $ponudaId);
        $stmt->execute();

        redirect_to('index.php?page=ponuda_stavke&ponuda_id=' . $ponudaId . '&izmenjeno=1');
    }

    redirect_to('index.php?page=ponuda_stavke&ponuda_id=' . $ponudaId . '&greska=1');
}

/* =========================
   SOFT BRISANJE STAVKE
========================= */
if (isset($_GET['obrisi_stavku'])) {
    $stavkaId = (int)$_GET['obrisi_stavku'];

    $stmt = $conn->prepare("UPDATE ponuda_stavke SET aktivna=0 WHERE id=? AND ponuda_id=?");
    $stmt->bind_param('ii', $stavkaId, $ponudaId);
    $stmt->execute();

    redirect_to('index.php?page=ponuda_stavke&ponuda_id=' . $ponudaId . '&obrisano=1');
}

/* =========================
   PODACI
========================= */
$elementi = db_all($conn, "SELECT id, naziv, kategorija FROM sifarnik_elemenata WHERE aktivan=1 ORDER BY kategorija, naziv");
$aktivnosti = db_all($conn, "SELECT id, naziv, tip FROM sifarnik_aktivnosti WHERE aktivna=1 ORDER BY tip, naziv");
$jedinice = db_all($conn, "SELECT id, naziv, oznaka FROM obracunske_jedinice WHERE aktivna=1 ORDER BY naziv");

$stavkaEdit = null;
if ($editId > 0) {
    $stavkaEdit = db_one($conn, "SELECT * FROM ponuda_stavke WHERE id=? AND ponuda_id=? AND aktivna=1", 'ii', [$editId, $ponudaId]);
}

$stavke = db_all(
    $conn,
    "SELECT ps.*, se.naziv AS element_naziv, sa.naziv AS aktivnost_naziv, oj.naziv AS jedinica_naziv, oj.oznaka AS jedinica
     FROM ponuda_stavke ps
     LEFT JOIN sifarnik_elemenata se ON se.id=ps.element_id
     LEFT JOIN sifarnik_aktivnosti sa ON sa.id=ps.aktivnost_id
     LEFT JOIN obracunske_jedinice oj ON oj.id=ps.jedinica_id
     WHERE ps.ponuda_id=? AND ps.aktivna=1
     ORDER BY se.naziv, sa.naziv",
    'i',
    [$ponudaId]
);

$ukupnoPoznato = 0;
$imaAutomatskiObracun = false;
foreach ($stavke as $s) {
    if ((float)$s['kolicina'] > 0) {
        $ukupnoPoznato += (float)$s['kolicina'] * (float)$s['cena'];
    } else {
        $imaAutomatskiObracun = true;
    }
}

require __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['dodato'])): ?><section class="card" style="border-left:4px solid green;">Stavka je dodata.</section><?php endif; ?>
<?php if (isset($_GET['izmenjeno'])): ?><section class="card" style="border-left:4px solid green;">Stavka je izmenjena.</section><?php endif; ?>
<?php if (isset($_GET['obrisano'])): ?><section class="card" style="border-left:4px solid orange;">Stavka je uklonjena.</section><?php endif; ?>
<?php if (isset($_GET['greska'])): ?><section class="card" style="border-left:4px solid red;">Popuni element, aktivnost i jedinicu obračuna.</section><?php endif; ?>

<section class="card">
    <div class="toolbar">
        <h2><?= $stavkaEdit ? 'Izmena stavke ponude' : 'Dodaj stavku ponude' ?></h2>
        <div class="actions">
            <?php if ($stavkaEdit): ?>
                <a class="btn btn-light" href="index.php?page=ponuda_stavke&ponuda_id=<?= $ponudaId ?>">Otkaži izmenu</a>
            <?php endif; ?>
            <a class="btn btn-light" href="index.php?page=ponuda&id=<?= $ponudaId ?>">← Ponuda</a>
        </div>
    </div>

    <form method="post" style="display:grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap:16px; align-items:end;">
        <?php if ($stavkaEdit): ?>
            <input type="hidden" name="izmeni_stavku" value="1">
            <input type="hidden" name="stavka_id" value="<?= (int)$stavkaEdit['id'] ?>">
        <?php else: ?>
            <input type="hidden" name="dodaj_stavku" value="1">
        <?php endif; ?>

        <div>
            <label>Element</label>
            <select name="element_id" required style="width:100%;">
                <option value="">-- izaberi --</option>
                <?php
                $kat = null;
                foreach ($elementi as $e):
                    if ($kat !== $e['kategorija']) {
                        if ($kat !== null) { echo '</optgroup>'; }
                        $kat = $e['kategorija'];
                        echo '<optgroup label="' . e($kat) . '">';
                    }
                    $selected = $stavkaEdit && (int)$stavkaEdit['element_id'] === (int)$e['id'];
                ?>
                    <option value="<?= (int)$e['id'] ?>" <?= $selected ? 'selected' : '' ?>><?= e($e['naziv']) ?></option>
                <?php endforeach; if ($kat !== null) { echo '</optgroup>'; } ?>
            </select>
        </div>

        <div>
            <label>Aktivnost</label>
            <select name="aktivnost_id" required style="width:100%;">
                <option value="">-- izaberi --</option>
                <?php foreach ($aktivnosti as $a): ?>
                    <?php $selected = $stavkaEdit && (int)$stavkaEdit['aktivnost_id'] === (int)$a['id']; ?>
                    <option value="<?= (int)$a['id'] ?>" <?= $selected ? 'selected' : '' ?>><?= e($a['naziv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Jedinica obračuna</label>
            <select name="jedinica_id" required style="width:100%;">
                <option value="">-- izaberi --</option>
                <?php foreach ($jedinice as $j): ?>
                    <?php $selected = $stavkaEdit && (int)$stavkaEdit['jedinica_id'] === (int)$j['id']; ?>
                    <option value="<?= (int)$j['id'] ?>" <?= $selected ? 'selected' : '' ?>><?= e($j['naziv']) ?> (<?= e($j['oznaka']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Količina za ovu ponudu</label>
            <input type="number"
                   step="0.01"
                   min="0"
                   name="kolicina"
                   value="<?= $stavkaEdit ? ((float)$stavkaEdit['kolicina'] > 0 ? e($stavkaEdit['kolicina']) : '') : '' ?>"
                   placeholder="prazno = automatski iz zgrade/programa"
                   style="width:100%;">
            <small class="muted">Za po stanu, po liftu, po aparatu i slično ostavi prazno.</small>
        </div>

        <div>
            <label>Jedinična cena bruto FCO</label>
            <input type="number" step="0.01" min="0" name="cena" value="<?= e($stavkaEdit['cena'] ?? 0) ?>" style="width:100%;">
        </div>

        <div>
            <label>Napomena</label>
            <input type="text" name="napomena" value="<?= e($stavkaEdit['napomena'] ?? '') ?>" style="width:100%;">
        </div>

        <div style="grid-column:1 / -1; display:flex; justify-content:flex-end; gap:10px;">
            <?php if ($stavkaEdit): ?>
                <button class="btn btn-primary" type="submit">Sačuvaj izmenu stavke</button>
            <?php else: ?>
                <button class="btn btn-primary" type="submit">Dodaj stavku</button>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="card">
    <div class="toolbar">
        <h2>Stavke ponude</h2>
    </div>

    <?php if (!$stavke): ?>
        <div class="empty">Nema stavki ponude.</div>
    <?php else: ?>
        <table class="table" style="width:100%; table-layout:fixed;">
            <thead>
                <tr>
                    <th style="text-align:left; width:20%;">Element</th>
                    <th style="text-align:left; width:20%;">Aktivnost</th>
                    <th style="text-align:left; width:12%;">Jedinica</th>
                    <th style="text-align:right; width:12%;">Količina</th>
                    <th style="text-align:right; width:14%;">Jed. cena</th>
                    <th style="text-align:right; width:14%;">Obračun</th>
                    <th style="text-align:left; width:8%;">Akcije</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stavke as $s): ?>
                <?php
                    $kolicina = (float)$s['kolicina'];
                    $cena = (float)$s['cena'];
                    $sum = $kolicina > 0 ? $kolicina * $cena : 0;
                ?>
                <tr>
                    <td><?= e($s['element_naziv'] ?? '') ?></td>
                    <td><?= e($s['aktivnost_naziv'] ?? ($s['naziv'] ?? '')) ?></td>
                    <td><?= e($s['jedinica_naziv'] ?? ($s['jedinica'] ?? '')) ?></td>
                    <td style="text-align:right;">
                        <?php if ($kolicina > 0): ?>
                            <?= e($s['kolicina']) ?>
                        <?php else: ?>
                            <span class="muted">auto</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;"><?= money_rs($cena) ?></td>
                    <td style="text-align:right;">
                        <?php if ($kolicina > 0): ?>
                            <?= money_rs($sum) ?>
                        <?php else: ?>
                            <span class="muted">iz programa</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="actions" style="display:flex; gap:6px; flex-wrap:wrap;">
                            <a class="btn btn-light btn-sm" href="index.php?page=ponuda_stavke&ponuda_id=<?= $ponudaId ?>&edit_id=<?= (int)$s['id'] ?>">Uredi</a>
                            <a class="btn btn-danger btn-sm" href="index.php?page=ponuda_stavke&ponuda_id=<?= $ponudaId ?>&obrisi_stavku=<?= (int)$s['id'] ?>" onclick="return confirm('Ukloniti stavku ponude?');">Obriši</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="text-align:right;">
            <strong>Poznati zbir: <?= money_rs($ukupnoPoznato) ?></strong>
            <?php if ($imaAutomatskiObracun): ?>
                <br><span class="muted">Deo stavki se obračunava automatski iz podataka zgrade/programa održavanja.</span>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
