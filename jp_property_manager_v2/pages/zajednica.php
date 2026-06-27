<?php
$szId = get_int('sz_id');
$z = db_one($conn, "SELECT * FROM stambene_zajednice WHERE id=?", 'i', [$szId]);
$title = $z ? ($z['naziv'] ?? 'Kartica zgrade') : 'Kartica zgrade';
$subtitle = $z ? ($z['adresa'] ?? '') : 'Zgrada nije pronađena.';
$godina = current_year();
$summary = $z ? finansijski_plan_summary($conn, $szId, $godina) : null;
require __DIR__ . '/../includes/header.php';
?>
<?php if (!$z): ?><div class="empty">Stambena zajednica nije pronađena.</div><?php else: ?>
<section class="grid grid-3">
    <div class="card stat stat-plan-today">
        <span class="muted">Planirano stanje danas</span>
        <strong><?= money_rs($summary['planiranoStanjeDanas'] ?? 0) ?></strong>
        <small>proporcionalno prema finansijskom planu <?= e($godina) ?></small>
    </div>
    <div class="card stat"><span class="muted">Posebni delovi</span><strong><?= e($z['broj_posebnih_delova'] ?? '-') ?></strong></div>
    <div class="card stat"><span class="muted">Garažna mesta</span><strong><?= e($z['broj_garaznih_mesta'] ?? '-') ?></strong></div>
</section>
<section class="grid grid-3" style="margin-top:18px">
    <a class="card module-card" href="index.php?page=finansijski_plan&sz_id=<?= $szId ?>"><div><h3>Finansijski plan</h3><p class="muted">Planirani prilivi, odlivi, stepen naplate, saldo i rebalans.</p></div><div class="icon">💰</div></a>
    <a class="card module-card" href="index.php?page=oprema&sz_id=<?= $szId ?>"><div><h3>Oprema</h3><p class="muted">Liftovi, hidranti, PP oprema, grejanje i druga oprema.</p></div><div class="icon">🔧</div></a>
    <a class="card module-card" href="index.php?page=program&sz_id=<?= $szId ?>"><div><h3>Program</h3><p class="muted">Plan održavanja po mesecima i realizacija.</p></div><div class="icon">📅</div></a>
    <a class="card module-card" href="index.php?page=kvarovi&sz_id=<?= $szId ?>"><div><h3>Kvarovi</h3><p class="muted">Vanredne intervencije i troškovi.</p></div><div class="icon">⚠️</div></a>
    <a class="card module-card" href="index.php?page=dokumentacija&sz_id=<?= $szId ?>"><div><h3>Dokumentacija</h3><p class="muted">PDF, fotografije, zapisnici, službene beleške.</p></div><div class="icon">📁</div></a>
    <a class="card module-card" href="index.php?page=izvestaji&sz_id=<?= $szId ?>"><div><h3>Izveštaji</h3><p class="muted">Realizacija programa i utrošak sredstava.</p></div><div class="icon">📈</div></a>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
