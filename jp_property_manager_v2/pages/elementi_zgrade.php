<?php
$szId = get_int('sz_id');

$title = 'Elementi zgrade';
$subtitle = 'Popis elemenata zgrade za automatsko formiranje programa održavanja.';

if ($szId <= 0) {
    die('Nije izabrana stambena zajednica.');
}

/* SNIMANJE ČEK-LISTE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['snimi_popis'])) {
    $oznaceni = $_POST['element'] ?? [];
    $kolicine = $_POST['kolicina'] ?? [];
    $napomene = $_POST['napomena'] ?? [];

    $sviElementi = db_all($conn, "SELECT id FROM sifarnik_elemenata WHERE aktivan=1");

    foreach ($sviElementi as $el) {
        $elementId = (int)$el['id'];
        $postoji = isset($oznaceni[$elementId]);

        $kolicina = isset($kolicine[$elementId]) ? (int)$kolicine[$elementId] : 1;
        if ($kolicina < 1) {
            $kolicina = 1;
        }

        $napomena = trim($napomene[$elementId] ?? '');

        $red = db_one(
            $conn,
            "SELECT id FROM oprema_zgrade WHERE sz_id=? AND element_id=? LIMIT 1",
            'ii',
            [$szId, $elementId]
        );

        if ($postoji) {
            if ($red) {
                $stmt = $conn->prepare("
                    UPDATE oprema_zgrade
                    SET kolicina=?, napomena=?, aktivna=1
                    WHERE id=?
                ");
                $stmt->bind_param('isi', $kolicina, $napomena, $red['id']);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO oprema_zgrade
                    (sz_id, element_id, kolicina, napomena, aktivna)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmt->bind_param('iiis', $szId, $elementId, $kolicina, $napomena);
                $stmt->execute();
            }
        } else {
            if ($red) {
                $stmt = $conn->prepare("
                    UPDATE oprema_zgrade
                    SET aktivna=0
                    WHERE id=?
                ");
                $stmt->bind_param('i', $red['id']);
                $stmt->execute();
            }
        }
    }

    redirect_to("index.php?page=elementi_zgrade&sz_id=" . $szId . "&snimljeno=1");
}

/* PODACI */
$elementi = db_all(
    $conn,
    "SELECT * FROM sifarnik_elemenata 
     WHERE aktivan=1 
     ORDER BY kategorija, naziv"
);

$opremaRows = db_all(
    $conn,
    "SELECT * FROM oprema_zgrade WHERE sz_id=?",
    'i',
    [$szId]
);

$oprema = [];
foreach ($opremaRows as $o) {
    $oprema[(int)$o['element_id']] = $o;
}

require __DIR__ . '/../includes/header.php';
?>

<?php if (isset($_GET['snimljeno'])): ?>
    <section class="card" style="border-left: 4px solid green;">
        Popis elemenata zgrade je sačuvan.
    </section>
<?php endif; ?>

<section class="card">
    <div class="toolbar">
        <h2>Popis elemenata zgrade</h2>
        <button class="btn btn-primary" form="popis-form" type="submit">
            Sačuvaj popis
        </button>
    </div>

    <form method="post" id="popis-form">
        <input type="hidden" name="snimi_popis" value="1">

        <?php
        $trenutnaKategorija = null;
        foreach ($elementi as $el):
            $elementId = (int)$el['id'];
            $red = $oprema[$elementId] ?? null;
            $aktivno = $red && ((int)($red['aktivna'] ?? 1) === 1);
            $kolicina = $red['kolicina'] ?? 1;
            $napomena = $red['napomena'] ?? '';

            if ($trenutnaKategorija !== $el['kategorija']):
                if ($trenutnaKategorija !== null) {
                    echo '</div>';
                }
                $trenutnaKategorija = $el['kategorija'];
        ?>
                <h3 style="margin-top: 25px;"><?= e($trenutnaKategorija) ?></h3>
                <div class="grid grid-1">
            <?php endif; ?>

            <article class="card card-muted" style="display:grid; grid-template-columns: 40px 1fr 150px 1fr; gap:12px; align-items:center;">
                <div>
                    <input type="checkbox"
                           name="element[<?= $elementId ?>]"
                           value="1"
                           <?= $aktivno ? 'checked' : '' ?>>
                </div>

                <div>
                    <strong><?= e($el['naziv']) ?></strong>
                </div>

                <div>
    <?php if ((int)($el['koristi_kolicinu'] ?? 1) === 1): ?>
        <div style="display:flex; gap:5px; align-items:center;">
            <button type="button" class="btn btn-light btn-sm qty-minus">−</button>
            <input type="number"
                   name="kolicina[<?= $elementId ?>]"
                   value="<?= e($kolicina) ?>"
                   min="1"
                   step="1"
                   style="width:70px; text-align:center;">
            <button type="button" class="btn btn-light btn-sm qty-plus">+</button>
        </div>
    <?php else: ?>
        <input type="hidden" name="kolicina[<?= $elementId ?>]" value="1">
        <span class="muted">bez količine</span>
    <?php endif; ?>
</div>

                <div>
                    <input type="text"
                           name="napomena[<?= $elementId ?>]"
                           value="<?= e($napomena) ?>"
                           placeholder="Napomena"
                           style="width:100%;">
                </div>
            </article>

        <?php endforeach; ?>

        <?php if ($trenutnaKategorija !== null): ?>
            </div>
        <?php endif; ?>

        <div style="margin-top:25px;">
            <button class="btn btn-primary" type="submit">Sačuvaj popis</button>
        </div>
    </form>
</section>

<script>
document.querySelectorAll('.qty-plus').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const input = btn.parentElement.querySelector('input[type="number"]');
        input.value = parseInt(input.value || '1', 10) + 1;
    });
});

document.querySelectorAll('.qty-minus').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const input = btn.parentElement.querySelector('input[type="number"]');
        const value = parseInt(input.value || '1', 10);
        input.value = Math.max(1, value - 1);
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>