<?php
$title = 'Stambene zajednice';
$subtitle = 'Spisak zgrada i ulaz u karticu svake zajednice.';
$zgrade = db_all($conn, "SELECT * FROM stambene_zajednice ORDER BY naziv");
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <div class="toolbar">
        <h2>Zgrade</h2>
        <a class="btn btn-primary" href="index.php?page=dodaj_zajednicu">+ Dodaj zgradu</a>
    </div>
    <?php if (!$zgrade): ?>
        <div class="empty">Nema unetih zgrada. Klikni na „Dodaj zgradu“ da uneseš prvu stambenu zajednicu.</div>
    <?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Naziv</th><th>Adresa</th><th>Tekući račun</th><th>Akcije</th></tr></thead><tbody>
    <?php foreach ($zgrade as $z): ?><tr>
        <td><strong><?= e($z['naziv'] ?? '') ?></strong></td><td><?= e($z['adresa'] ?? '') ?></td><td><?= e($z['tekuci_racun'] ?? '') ?></td>
        <td><div class="actions"><a class="btn btn-primary btn-sm" href="index.php?page=zajednica&sz_id=<?= (int)$z['id'] ?>">Otvori</a></div></td>
    </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
