<?php
$szId = get_int('sz_id');
$godina = isset($_GET['godina']) ? (int)$_GET['godina'] : current_year();
$title = 'Finansijski plan';
ensure_finansijski_plan_schema($conn);

$summary = $szId ? finansijski_plan_summary($conn, $szId, $godina) : null;
$z = $summary['zgrada'] ?? null;
$plan = $summary['plan'] ?? null;
$subtitle = $z ? (($z['naziv'] ?? '') . ' — planirani prilivi i odlivi za ' . $godina . '. godinu') : 'Stambena zajednica nije pronađena.';

if ($z && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_plan') {
    $plan = get_or_create_finansijski_plan($conn, $szId, $godina);
    $tekuce = (float)str_replace(',', '.', post_value('tekuce_po_delu', 0));
    $upravljanje = (float)str_replace(',', '.', post_value('upravljanje_po_delu', 0));
    $garaza = (float)str_replace(',', '.', post_value('garaza_po_mestu', 0));
    $invest = (float)str_replace(',', '.', post_value('investiciono_po_m2', 0));
    $investGaraza = (float)str_replace(',', '.', post_value('investiciono_garaza_po_m2', 0));
    $naplata = (float)str_replace(',', '.', post_value('stepen_naplate', 100));
    $nepred = (float)str_replace(',', '.', post_value('nepredvidjeni_proc', 0));
    $napomena = trim(post_value('napomena', ''));
    $stmt = $conn->prepare("UPDATE finansijski_planovi SET tekuce_po_delu=?, upravljanje_po_delu=?, garaza_po_mestu=?, investiciono_po_m2=?, investiciono_garaza_po_m2=?, stepen_naplate=?, nepredvidjeni_proc=?, napomena=? WHERE id=?");
    $stmt->bind_param('dddddddsi', $tekuce, $upravljanje, $garaza, $invest, $investGaraza, $naplata, $nepred, $napomena, $plan['id']);
    $stmt->execute();
    redirect_to("index.php?page=finansijski_plan&sz_id=$szId&godina=$godina");
}

require __DIR__ . '/../includes/header.php';
?>
<?php if (!$z): ?>
    <div class="empty">Stambena zajednica nije pronađena.</div>
<?php else:
    $p = fn($field, $default=0) => $plan && isset($plan[$field]) ? (float)$plan[$field] : $default;
    $stavke = $summary['stavke'];
    $metrics = $summary['metrics'];
    $rebalansi = db_all($conn, "SELECT * FROM finansijski_plan_rebalansi WHERE plan_id=? ORDER BY datum DESC, id DESC", 'i', [(int)$plan['id']]);
?>
<section class="finance-hero">
    <div class="finance-hero-main">
        <span class="eyebrow">Finansijski plan <?= e($godina) ?></span>
        <h2><?= e($z['naziv'] ?? 'Stambena zajednica') ?></h2>
        <p>Prilivi se računaju iz mesečnih stavki zaduženja, a odlivi iz planiranih troškova. Stavka može početi ili prestati u bilo kom mesecu.</p>
    </div>
    <form class="year-switch" method="get">
        <input type="hidden" name="page" value="finansijski_plan">
        <input type="hidden" name="sz_id" value="<?= $szId ?>">
        <label>Godina</label>
        <input type="number" name="godina" value="<?= $godina ?>" min="2020" max="2100">
        <button class="btn btn-light" type="submit">Prikaži</button>
    </form>
</section>

<section class="grid grid-4 finance-stats">
    <div class="card stat"><span class="muted">Planirano stanje danas</span><strong><?= money_rs($summary['planiranoStanjeDanas']) ?></strong></div>
    <div class="card stat"><span class="muted">Očekivani priliv</span><strong><?= money_rs($summary['ocekivaniPriliv']) ?></strong></div>
    <div class="card stat"><span class="muted">Planirani odlivi</span><strong><?= money_rs($summary['ukupniOdlivi']) ?></strong></div>
    <div class="card stat <?= $summary['saldoPlana'] >= 0 ? 'stat-good' : 'stat-bad' ?>"><span class="muted">Saldo plana</span><strong><?= money_rs($summary['saldoPlana']) ?></strong></div>
</section>

<section class="card finance-summary-card" style="margin-top:18px">
    <div class="toolbar">
        <h2>Zbir finansijskog plana</h2>
        <div class="actions"><a class="btn btn-warning btn-sm" href="index.php?page=finansijski_plan_rebalans&sz_id=<?= $szId ?>&godina=<?= $godina ?>">↻ Rebalans</a><span class="badge">mesečna logika</span></div>
    </div>
    <div class="finance-summary-grid fixed-labels">
        <div><span class="summary-label">Planirani prilivi</span><strong><?= money_rs($summary['planiraniPriliv']) ?></strong><small>pre stepena naplate</small></div>
        <div><span class="summary-label">Očekivani priliv</span><strong><?= money_rs($summary['ocekivaniPriliv']) ?></strong><small><?= e($p('stepen_naplate',100)) ?>% naplate</small></div>
        <div><span class="summary-label">Planirani odlivi</span><strong><?= money_rs($summary['ukupniOdlivi']) ?></strong><small>sa nepredviđenim</small></div>
        <div class="<?= $summary['saldoPlana'] >= 0 ? 'positive' : 'negative' ?>"><span class="summary-label">Saldo plana</span><strong><?= money_rs($summary['saldoPlana']) ?></strong><small>priliv − odlivi</small></div>
        <div class="<?= $summary['ocekivanoKrajGodine'] >= 0 ? 'positive' : 'negative' ?>"><span class="summary-label">Stanje 31.12.</span><strong><?= money_rs($summary['ocekivanoKrajGodine']) ?></strong><small>po planu</small></div>
    </div>
</section>

<section class="card" style="margin-top:18px">
    <div class="toolbar"><h2>Vremenska osa po mesecima</h2><span class="badge">planirano za svaki mesec</span></div>
    <div class="month-grid">
        <?php foreach ($summary['monthly'] as $m): ?>
        <div class="month-card <?= $m['saldo'] >= 0 ? 'month-good' : 'month-bad' ?>">
            <strong><?= e($m['naziv']) ?></strong>
            <span>Priliv: <?= money_rs($m['ocekivani_priliv']) ?></span>
            <span>Odliv: <?= money_rs($m['odliv']) ?></span>
            <b><?= money_rs($m['saldo']) ?></b>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="grid grid-2" style="margin-top:18px">
    <div class="card">
        <div class="toolbar"><h2>Osnovne mesečne stavke</h2><span class="badge">formiranje zgrade</span></div>
        <form method="post" class="form-grid compact-form">
            <input type="hidden" name="action" value="update_plan">
            <div class="field"><label>Tekuće održavanje / poseban deo</label><input type="number" step="0.01" name="tekuce_po_delu" value="<?= e($p('tekuce_po_delu')) ?>"></div>
            <div class="field"><label>Upravljanje / poseban deo</label><input type="number" step="0.01" name="upravljanje_po_delu" value="<?= e($p('upravljanje_po_delu')) ?>"></div>
            <div class="field"><label>Tekuće održavanje garaža / mesto</label><input type="number" step="0.01" name="garaza_po_mestu" value="<?= e($p('garaza_po_mestu')) ?>"></div>
            <div class="field"><label>Investiciono održavanje / m² posebnih delova</label><input type="number" step="0.01" name="investiciono_po_m2" value="<?= e($p('investiciono_po_m2')) ?>"></div>
            <div class="field"><label>Investiciono održavanje / m² garažnog prostora</label><input type="number" step="0.01" name="investiciono_garaza_po_m2" value="<?= e($p('investiciono_garaza_po_m2')) ?>"></div>
            <div class="field"><label>Planirani stepen naplate (%)</label><input type="number" step="0.01" name="stepen_naplate" value="<?= e($p('stepen_naplate',100)) ?>"></div>
            <div class="field"><label>Nepredviđeni troškovi (%)</label><input type="number" step="0.01" name="nepredvidjeni_proc" value="<?= e($p('nepredvidjeni_proc')) ?>"></div>
            <div class="field full"><label>Napomena</label><textarea name="napomena" rows="2"><?= e($plan['napomena'] ?? '') ?></textarea></div>
            <div class="full actions"><button class="btn btn-primary" type="submit">💾 Sačuvaj osnovne stavke</button></div>
        </form>
    </div>
    <div class="card">
        <div class="toolbar"><h2>Osnova obračuna</h2><span class="badge">iz kartice zgrade</span></div>
        <div class="mini-metrics">
            <div><span>Posebni delovi</span><strong><?= e($summary['brojDelova']) ?></strong></div>
            <div><span>Garažna mesta</span><strong><?= e($summary['brojGaraza']) ?></strong></div>
            <div><span>Površina posebnih delova</span><strong><?= e($summary['povrsinaDelova']) ?> m²</strong></div>
            <div><span>Površina garaža</span><strong><?= e($summary['povrsinaGaraza']) ?> m²</strong></div>
        </div>
        <div class="result-box">
            <span>Dodatni prilivi</span><strong><?= money_rs($summary['dodatniPrilivi']) ?></strong>
            <span>Odlivi bez rezerve</span><strong><?= money_rs($summary['planiraniOdlivi']) ?></strong>
            <span>Nepredviđeno</span><strong><?= money_rs($summary['nepredvidjeni']) ?></strong>
        </div>
    </div>
</section>

<section class="card" style="margin-top:18px">
    <div class="toolbar"><h2>Analitika planiranih priliva</h2><a class="btn btn-primary" href="index.php?page=finansijski_plan_stavka&sz_id=<?= $szId ?>&godina=<?= $godina ?>&tip=priliv">+ Dodaj stavku priliva</a></div>
    <div class="table-wrap"><table><thead><tr><th>Stavka</th><th>Obračun</th><th>Period</th><th>Godišnje</th><th>Akcija</th></tr></thead><tbody>
        <?php foreach($summary['basePrilivi'] as $r): ?>
        <tr><td><strong><?= e($r['naziv']) ?></strong><div class="muted">osnovna stavka</div></td><td><?= e(stavka_osnov_label($r['osnov'])) ?>: <?= e($r['osnovica']) ?> × <?= money_rs($r['iznos']) ?></td><td>JAN–DEC</td><td><strong><?= money_rs($r['total']) ?></strong></td><td><span class="muted">menja se u osnovnim stavkama</span></td></tr>
        <?php endforeach; ?>
        <?php foreach($stavke as $s): if(($s['tip'] ?? '') !== 'priliv') continue; ?>
        <tr>
            <td><strong><?= e($s['naziv']) ?></strong><div class="muted"><?= e($s['grupa'] ?? 'Dodatno') ?></div></td>
            <td><?= e(stavka_formula($s, $metrics)) ?><div class="muted"><?= e(stavka_osnov_label($s['osnov'] ?? 'fiksno')) ?></div></td>
            <td><?= e(stavka_period_label($s)) ?></td>
            <td><strong><?= money_rs(stavka_total($s, $metrics)) ?></strong></td>
            <td><div class="actions row-actions"><a class="btn btn-light btn-sm" href="index.php?page=finansijski_plan_stavka&id=<?= (int)$s['id'] ?>&sz_id=<?= $szId ?>&godina=<?= $godina ?>">Izmeni</a><a class="btn btn-danger btn-sm" onclick="return confirm('Ukloniti ovu stavku?')" href="index.php?page=finansijski_plan_obrisi&id=<?= (int)$s['id'] ?>&sz_id=<?= $szId ?>&godina=<?= $godina ?>">Ukloni</a></div></td>
        </tr>
        <?php endforeach; ?>
        <tr class="summary-row"><td colspan="3">Ukupno planirani prilivi</td><td><strong><?= money_rs($summary['planiraniPriliv']) ?></strong></td><td></td></tr>
        <tr class="summary-row"><td colspan="3">Očekivani priliv po stepenu naplate</td><td><strong><?= money_rs($summary['ocekivaniPriliv']) ?></strong></td><td></td></tr>
    </tbody></table></div>
</section>

<section class="card" style="margin-top:18px">
    <div class="toolbar"><h2>Planirani odlivi</h2><a class="btn btn-primary" href="index.php?page=finansijski_plan_stavka&sz_id=<?= $szId ?>&godina=<?= $godina ?>&tip=odliv">+ Dodaj stavku odliva</a></div>
    <div class="table-wrap"><table><thead><tr><th>Stavka</th><th>Obračun</th><th>Period</th><th>Godišnje</th><th>Akcija</th></tr></thead><tbody>
        <?php foreach($stavke as $s): if(($s['tip'] ?? '') !== 'odliv') continue; ?>
        <tr>
            <td><strong><?= e($s['naziv']) ?></strong><?= (int)$s['predefinisana'] ? '<div class="muted">Predefinisana stavka</div>' : '' ?></td>
            <td><?= e(stavka_formula($s, $metrics)) ?><div class="muted"><?= e(stavka_osnov_label($s['osnov'] ?? 'fiksno')) ?></div></td>
            <td><?= e(stavka_period_label($s)) ?></td>
            <td><strong><?= money_rs(stavka_total($s, $metrics)) ?></strong></td>
            <td><div class="actions"><a class="btn btn-light btn-sm" href="index.php?page=finansijski_plan_stavka&id=<?= (int)$s['id'] ?>&sz_id=<?= $szId ?>&godina=<?= $godina ?>">Izmeni</a><a class="btn btn-danger btn-sm" onclick="return confirm('Ukloniti ovu stavku iz finansijskog plana?')" href="index.php?page=finansijski_plan_obrisi&id=<?= (int)$s['id'] ?>&sz_id=<?= $szId ?>&godina=<?= $godina ?>">Ukloni</a></div></td>
        </tr>
        <?php endforeach; ?>
        <tr class="summary-row"><td colspan="3">Nepredviđeni troškovi (<?= e($p('nepredvidjeni_proc')) ?>%)</td><td><strong><?= money_rs($summary['nepredvidjeni']) ?></strong></td><td></td></tr>
        <tr class="summary-row total"><td colspan="3">Ukupno planirani odlivi</td><td><strong><?= money_rs($summary['ukupniOdlivi']) ?></strong></td><td></td></tr>
        <tr class="summary-row <?= $summary['saldoPlana'] >= 0 ? 'positive' : 'negative' ?>"><td colspan="3">Saldo plana</td><td><strong><?= money_rs($summary['saldoPlana']) ?></strong></td><td></td></tr>
    </tbody></table></div>
</section>

<section class="card" style="margin-top:18px">
    <div class="toolbar"><h2>Rebalansi finansijskog plana</h2><a class="btn btn-warning" href="index.php?page=finansijski_plan_rebalans&sz_id=<?= $szId ?>&godina=<?= $godina ?>">↻ Novi rebalans</a></div>
    <?php if (!$rebalansi): ?><div class="empty">Za ovu godinu još nije evidentiran rebalans.</div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Datum</th><th>Razlog / napomena</th></tr></thead><tbody><?php foreach($rebalansi as $r): ?><tr><td><strong><?= e(date('d.m.Y.', strtotime($r['datum']))) ?></strong></td><td><?= nl2br(e($r['razlog'] ?? '')) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
