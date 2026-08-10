<?php
$cenovnikId = get_int('cenovnik_id');

$title = 'Stavke cenovnika';
$subtitle = 'Unos bruto FCO cena po elementu, aktivnosti i obračunskoj jedinici.';

if ($cenovnikId <= 0) {
    die('Nije izabran cenovnik.');
}

$izvodjacNazivCol = first_existing_column($conn, 'izvodjaci', ['naziv', 'ime', 'naziv_firme', 'firma'], 'naziv');

$cenovnik = db_one(
    $conn,
    "SELECT c.*, i.`$izvodjacNazivCol` AS izvodjac_naziv
     FROM cenovnici c
     JOIN izvodjaci i ON i.id=c.izvodjac_id
     WHERE c.id=?",
    'i',
    [$cenovnikId]
);

if (!$cenovnik) {
    die('Cenovnik nije pronađen.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_stavku'])) {
    $elementId = (int)($_POST['element_id'] ?? 0);
    $aktivnostId = (int)($_POST['aktivnost_id'] ?? 0);
    $jedinicaId = (int)($_POST['jedinica_id'] ?? 0);
    $cena = numeric_value($_POST['cena'] ?? 0);
    $napomena = trim($_POST['napomena'] ?? '');

    if ($elementId > 0 && $aktivnostId > 0 && $jedinicaId > 0 && $cena >= 0) {
        $stmt = $conn->prepare("
            INSERT INTO cenovnik_stavke
            (cenovnik_id, element_id, aktivnost_id, jedinica_id, cena, napomena, aktivna)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->bind_param('iiiids', $cenovnikId, $elementId, $aktivnostId, $jedinicaId, $cena, $napomena);
        $stmt->execute();

        redirect_to('index.php?page=cenovnik_stavke&cenovnik_id=' . $cenovnikId . '&dodato=1');
    }

    redirect_to('index.php?page=cenovnik_stavke&cenovnik_id=' . $cenovnikId . '&greska=1');
}

if (isset($_GET['obrisi_stavku'])) {
    $stavkaId = (int)$_GET['obrisi_stavku'];

    $stmt = $conn->prepare("
        UPDATE cenovnik_stavke
        SET aktivna=0
        WHERE id=? AND cenovnik_id=?
    ");
    $stmt->bind_param('ii', $stavkaId, $cenovnikId);
    $stmt->execute();

    redirect_to('index.php?page=cenovnik_stavke&cenovnik_id=' . $cenovnikId . '&obrisano=1');
}

$elementi = db_all(
    $conn,
    "SELECT id, naziv, kategorija
     FROM sifarnik_elemenata
     WHERE aktivan=1
     ORDER BY kategorija, naziv"
);

$aktivnosti = db_all(
    $conn,
    "SELECT id, naziv, tip
     FROM sifarnik_aktivnosti
     WHERE aktivna=1
     ORDER BY tip, naziv"
);

$jedinice = db_all(
    $conn,
    "SELECT id, naziv, oznaka
     FROM obracunske_jedinice
     WHERE aktivna=1
     ORDER BY naziv"
);

$stavke = db_all(
    $conn,
    "SELECT cs.*, se.naziv AS element_naziv, se.kategorija,
            sa.naziv AS aktivnost_naziv, sa.tip AS aktivnost_tip,
            oj.naziv AS jedinica_naziv, oj.oznaka AS jedinica_oznaka
     FROM cenovnik_stavke cs
     JOIN sifarnik_elemenata se ON se.id=cs.element_id
     JOIN sifarnik_aktivnosti sa ON sa.id=cs.aktivnost_id
     JOIN obracunske_jedinice oj ON oj.id=cs.jedinica_id
     WHERE cs.cenovnik_id=? AND cs.aktivna=1
     ORDER BY se.kategorija, se.naziv, sa.naziv",
    'i',
    [$cenovnikId]
);

require __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['dodato'])): ?>
    <section class="card" style="border-left:4px solid green;">Stavka je dodata.</section>
<?php endif; ?>

<?php if (isset($_GET['obrisano'])): ?>
    <section class="card" style="border-left:4px solid orange;">Stavka je uklonjena.</section>
<?php endif; ?>

<?php if (isset($_GET['greska'])): ?>
    <section class="card" style="border-left:4px solid red;">Popuni element, aktivnost, jedinicu i cenu.</section>
<?php endif; ?>

<section class="card">
    <div class="toolbar">
        <h2><?= e($cenovnik['naziv']) ?></h2>
        <div class="actions">
            <a class="btn btn-light" href="index.php?page=cenovnik&id=<?= $cenovnikId ?>">← Cenovnik</a>
            <a class="btn btn-light" href="index.php?page=cenovnici">Svi cenovnici</a>
        </div>
    </div>

    <p class="muted">Izvođač: <strong><?= e($cenovnik['izvodjac_naziv']) ?></strong></p>
</section>

<section class="card">
    <div class="toolbar">
        <h2>Dodaj stavku</h2>
    </div>

    <form method="post" class="form-grid">
        <input type="hidden" name="dodaj_stavku" value="1">

        <div>
            <label>Element</label>
            <select name="element_id" required>
                <option value="">-- izaberi element --</option>
                <?php
                $kat = null;
                foreach ($elementi as $el):
                    if ($kat !== $el['kategorija']):
                        if ($kat !== null) {
                            echo '</optgroup>';
                        }
                        $kat = $el['kategorija'];
                        echo '<optgroup label="' . e($kat) . '">';
                    endif;
                ?>
                    <option value="<?= (int)$el['id'] ?>"><?= e($el['naziv']) ?></option>
                <?php endforeach; ?>
                <?php if ($kat !== null) echo '</optgroup>'; ?>
            </select>
        </div>

        <div>
            <label>Aktivnost</label>
            <select name="aktivnost_id" required>
                <option value="">-- izaberi aktivnost --</option>
                <?php foreach ($aktivnosti as $a): ?>
                    <option value="<?= (int)$a['id'] ?>">
                        <?= e($a['naziv']) ?><?= !empty($a['tip']) ? ' (' . e($a['tip']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Jedinica</label>
            <select name="jedinica_id" required>
                <option value="">-- jedinica --</option>
                <?php foreach ($jedinice as $j): ?>
                    <option value="<?= (int)$j['id'] ?>"><?= e($j['naziv']) ?> / <?= e($j['oznaka']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Cena / bruto FCO</label>
            <input type="number" step="0.01" min="0" name="cena" required>
        </div>


        <div>
            <label>Napomena</label>
            <input type="text" name="napomena" placeholder="npr. FCO, bruto cena, bez posebnih uslova...">
        </div>

        <div>
            <button class="btn btn-primary" type="submit">Dodaj stavku</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="toolbar">
        <h2>Stavke cenovnika</h2>
    </div>

    <?php if (!$stavke): ?>
        <div class="empty">Još nema stavki u cenovniku.</div>
    <?php else: ?>
        <table class="table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left;">Kategorija</th>
                    <th style="text-align:left;">Element</th>
                    <th style="text-align:left;">Aktivnost</th>
                    <th style="text-align:right;">Cena / bruto FCO</th>
                    <th style="text-align:center;">Jedinica</th>
                    <th style="text-align:left;">Napomena</th>
                    <th style="text-align:right;">Akcije</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stavke as $s): ?>
                    <tr>
                        <td><?= e($s['kategorija']) ?></td>
                        <td><?= e($s['element_naziv']) ?></td>
                        <td><?= e($s['aktivnost_naziv']) ?></td>
                        <td style="text-align:right;"><?= money_rs($s['cena']) ?></td>
                        <td style="text-align:center;"><?= e($s['jedinica_oznaka']) ?></td>
                        <td><?= e($s['napomena'] ?? '') ?></td>
                        <td style="text-align:right;">
                            <a class="btn btn-danger btn-sm"
                               href="index.php?page=cenovnik_stavke&cenovnik_id=<?= $cenovnikId ?>&obrisi_stavku=<?= (int)$s['id'] ?>"
                               data-confirm="Ukloniti stavku iz cenovnika?">
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
