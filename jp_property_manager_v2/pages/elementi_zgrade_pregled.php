<?php
$szId = get_int('sz_id');

$title = 'Pregled elemenata zgrade';
$subtitle = 'Pregled evidentiranih elemenata zgrade bez uređivanja.';

if ($szId <= 0) {
    die('Nije izabrana stambena zajednica.');
}

$elementi = db_all(
    $conn,
    "SELECT oz.*, se.naziv AS element_naziv, se.kategorija, se.koristi_kolicinu
     FROM oprema_zgrade oz
     JOIN sifarnik_elemenata se ON se.id = oz.element_id
     WHERE oz.sz_id=? 
       AND oz.aktivna=1
     ORDER BY se.kategorija, se.naziv",
    'i',
    [$szId]
);

require __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <div class="toolbar">
        <h2>Evidentirani elementi</h2>
        <a class="btn btn-primary" href="index.php?page=elementi_zgrade&sz_id=<?= $szId ?>">Uredi popis</a>
    </div>

    <?php if (!$elementi): ?>
        <div class="empty">Još nema evidentiranih elemenata za ovu zgradu.</div>
    <?php else: ?>

        <?php
        $trenutnaKategorija = null;
        foreach ($elementi as $el):
            if ($trenutnaKategorija !== $el['kategorija']):
                if ($trenutnaKategorija !== null) {
                    echo '</div>';
                }
                $trenutnaKategorija = $el['kategorija'];
        ?>
                <h3 style="margin-top: 25px;"><?= e($trenutnaKategorija) ?></h3>
                <div class="grid grid-3">
            <?php endif; ?>

            <article class="card card-muted">
                <h3><?= e($el['element_naziv']) ?></h3>

                <?php if ((int)($el['koristi_kolicinu'] ?? 1) === 1): ?>
                    <p class="muted">Količina: <?= e($el['kolicina'] ?? 1) ?></p>
                <?php endif; ?>

                <?php if (!empty($el['napomena'])): ?>
                    <p><?= e($el['napomena']) ?></p>
                <?php endif; ?>
            </article>

        <?php endforeach; ?>

        <?php if ($trenutnaKategorija !== null): ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>