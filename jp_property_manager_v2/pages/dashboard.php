<?php
$title = 'Početna';
$subtitle = 'Centralni pregled aplikacije i brz pristup modulima.';
$zgrade = db_all($conn, "SELECT id, naziv, adresa FROM stambene_zajednice ORDER BY naziv LIMIT 6");
require __DIR__ . '/../includes/header.php';
?>
<section class="grid grid-3">
    <a class="card module-card" href="index.php?page=zajednice"><div><h3>Stambene zajednice</h3><p class="muted">Evidencija osnovnih podataka, početnog stanja i kartica zgrada.</p></div><div class="icon">🏢</div></a>
    <a class="card module-card" href="index.php?page=izvodjaci"><div><h3>Izvođači</h3><p class="muted">Kategorije radova, kontakti i poslovni podaci.</p></div><div class="icon">👷</div></a>
    <a class="card module-card" href="index.php?page=ponude"><div><h3>Ponude</h3><p class="muted">Ponude, stavke, dokumenti, slike i PDF prilozi.</p></div><div class="icon">📑</div></a>
</section>
<section class="card" style="margin-top:18px">
    <div class="toolbar"><h2>Skoro korišćene zgrade</h2><a class="btn btn-primary" href="index.php?page=zajednice">Sve zgrade</a></div>
    <?php if (!$zgrade): ?>
        <div class="empty">Još nema unetih stambenih zajednica.</div>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($zgrade as $z): ?>
                <a class="card card-muted" href="index.php?page=zajednica&sz_id=<?= (int)$z['id'] ?>">
                    <h3><?= e($z['naziv']) ?></h3>
                    <p class="muted"><?= e($z['adresa'] ?? '') ?></p>
                    <span class="badge">Otvori karticu</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
