<?php
$szId = get_int('sz_id');
$title = 'Program održavanja';
$subtitle = 'Plan, meseci, ponude, dokumentacija i realizacija programa.';
$aktivnosti = db_all($conn, "SELECT * FROM program_odrzavanja WHERE sz_id=? ORDER BY mesec, id", 'i', [$szId]);
$meseci = ['Januar','Februar','Mart','April','Maj','Jun','Jul','Avgust','Septembar','Oktobar','Novembar','Decembar'];
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <div class="toolbar"><h2>Vremenski plan po mesecima</h2><div class="actions"><a class="btn btn-primary" href="generisi_program.php?sz_id=<?= $szId ?>">Generiši program</a><a class="btn btn-light" href="program_odrzavanja.php?sz_id=<?= $szId ?>">Stari prikaz</a></div></div>
    <div class="timeline">
        <?php for($i=1;$i<=12;$i++): ?>
        <div class="month"><strong><?= $meseci[$i-1] ?></strong>
            <?php $ima=false; foreach($aktivnosti as $a): if((int)($a['mesec'] ?? 0)===$i): $ima=true; ?>
                <span class="badge" style="margin:2px 0"><?= e($a['naziv'] ?? $a['aktivnost'] ?? 'Aktivnost') ?></span><br>
            <?php endif; endforeach; if(!$ima): ?><span class="muted">Nema aktivnosti</span><?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</section>
<section class="card" style="margin-top:18px">
    <div class="toolbar"><h2>Aktivnosti programa</h2><a class="btn btn-primary" href="program_odrzavanja.php?sz_id=<?= $szId ?>">+ Dodaj aktivnost</a></div>
    <?php if(!$aktivnosti): ?><div class="empty">Program još nije formiran.</div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Aktivnost</th><th>Kategorija</th><th>Mesec</th><th>Ponude</th><th>Akcije</th></tr></thead><tbody>
    <?php foreach($aktivnosti as $a): ?><tr><td><strong><?= e($a['naziv'] ?? $a['aktivnost'] ?? '') ?></strong></td><td><?= e($a['kategorija'] ?? '') ?></td><td><?= e($a['mesec'] ?? '') ?></td><td><a class="btn btn-light btn-sm" href="program_ponude.php?program_id=<?= (int)$a['id'] ?>&sz_id=<?= $szId ?>">Ponude</a></td><td><div class="actions"><a class="btn btn-success btn-sm" href="#">Realizuj</a><a class="btn btn-danger btn-sm" href="#" data-confirm="Ukloniti stavku programa?">Ukloni</a></div></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
