<?php
$szId = get_int('sz_id');
$title = 'Oprema zgrade';
$subtitle = 'Oprema je osnov za automatsko formiranje programa održavanja.';
$oprema = db_all($conn, "SELECT * FROM oprema_zgrade WHERE sz_id=? AND (aktivna=1 OR aktivna IS NULL) ORDER BY tip", 'i', [$szId]);
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <div class="toolbar"><h2>Aktivna oprema</h2><a class="btn btn-primary" href="oprema.php?sz_id=<?= $szId ?>">+ Dodaj opremu</a></div>
    <?php if (!$oprema): ?><div class="empty">Još nema unete opreme za ovu zgradu.</div><?php else: ?>
    <div class="grid grid-3">
        <?php foreach($oprema as $o): ?><article class="card card-muted"><h3><?= e($o['tip'] ?? '') ?></h3><p class="muted">Količina: <?= e($o['kolicina'] ?? 1) ?></p><p><?= e($o['napomena'] ?? '') ?></p><div class="actions"><a class="btn btn-danger btn-sm" href="obrisi_opremu.php?id=<?= (int)$o['id'] ?>&sz_id=<?= $szId ?>" data-confirm="Ukloniti opremu iz zgrade?">Ukloni</a></div></article><?php endforeach; ?>
    </div><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
