<?php
$szId = get_int('sz_id');
$godina = isset($_GET['godina']) ? (int)$_GET['godina'] : current_year();
$z = db_one($conn, "SELECT * FROM stambene_zajednice WHERE id=?", 'i', [$szId]);
$title = 'Finansijski plan';
$subtitle = $z ? (($z['naziv'] ?? '') . ' — planirani prilivi i odlivi za ' . $godina . '. godinu') : 'Stambena zajednica nije pronađena.';

ensure_finansijski_plan_schema($conn);

if ($z && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_plan') {
    $plan = get_or_create_finansijski_plan($conn, $szId, $godina);
    $tekuce = (float)str_replace(',', '.', post_value('tekuce_po_delu', 0));
    $upravljanje = (float)str_replace(',', '.', post_value('upravljanje_po_delu', 0));
    $garaza = (float)str_replace(',', '.', post_value('garaza_po_mestu', 0));
    $invest = (float)str_replace(',', '.', post_value('investiciono_po_m2', 0));
    $naplata = (float)str_replace(',', '.', post_value('stepen_naplate', 100));
    $nepred = (float)str_replace(',', '.', post_value('nepredvidjeni_proc', 0));
    $napomena = trim(post_value('napomena', ''));
    $stmt = $conn->prepare("UPDATE finansijski_planovi SET tekuce_po_delu=?, upravljanje_po_delu=?, garaza_po_mestu=?, investiciono_po_m2=?, stepen_naplate=?, nepredvidjeni_proc=?, napomena=? WHERE id=?");
    $stmt->bind_param('ddddddsi', $tekuce, $upravljanje, $garaza, $invest, $naplata, $nepred, $napomena, $plan['id']);
    $stmt->execute();
    redirect_to("index.php?page=finansijski_plan&sz_id=$szId&godina=$godina");
}

$plan = $z ? get_or_create_finansijski_plan($conn, $szId, $godina) : null;
if ($plan && $z) {
    $plan = sync_finansijski_plan_from_v1_budzet($conn, $plan, $z, $godina);
}
$stavke = $plan ? db_all($conn, "SELECT * FROM finansijski_plan_stavke WHERE plan_id=? AND aktivna=1 ORDER BY tip, predefinisana DESC, grupa, naziv", 'i', [(int)$plan['id']]) : [];

$brojDelova = $z ? get_building_metric($z, $conn, ['broj_posebnih_delova','broj_delova','broj_stanova'], 0) : 0;
$brojGaraza = $z ? get_building_metric($z, $conn, ['broj_garaznih_mesta','broj_garaza'], 0) : 0;
$povrsinaDelova = $z ? get_building_metric($z, $conn, ['ukupna_povrsina_posebnih_delova','povrsina_posebnih_delova','ukupna_povrsina'], 0) : 0;
$povrsinaGaraza = $z ? get_building_metric($z, $conn, ['ukupna_povrsina_garaznih_mesta','povrsina_garaznih_mesta'], 0) : 0;
$ukupnaPovrsina = $povrsinaDelova + $povrsinaGaraza;
$stanjeCol = $z ? first_existing_column($conn, 'stambene_zajednice', ['pocetno_stanje','pocetno_stanje_racuna','stanje_racuna'], null) : null;
$pocetnoStanje = $z && $stanjeCol ? (float)$z[$stanjeCol] : 0;
if ($z && table_exists($conn, 'budzeti')) {
    $legacyBudzet = db_one($conn, "SELECT pocetno_stanje_racuna FROM budzeti WHERE sz_id=? AND godina=? ORDER BY (status='aktivan') DESC, id DESC LIMIT 1", 'ii', [$szId, $godina]);
    if ($legacyBudzet && isset($legacyBudzet['pocetno_stanje_racuna'])) {
        $pocetnoStanje = (float)$legacyBudzet['pocetno_stanje_racuna'];
    }
}

$p = fn($field, $default=0) => $plan && isset($plan[$field]) ? (float)$plan[$field] : $default;
$analitikaPriliva = [
    ['Tekuće održavanje', 'po posebnom delu', $brojDelova, $p('tekuce_po_delu'), 12],
    ['Upravljanje', 'po posebnom delu', $brojDelova, $p('upravljanje_po_delu'), 12],
    ['Tekuće održavanje garaža', 'po garažnom mestu', $brojGaraza, $p('garaza_po_mestu'), 12],
    ['Investiciono održavanje', 'po m² posebnih delova i garaža', $ukupnaPovrsina, $p('investiciono_po_m2'), 12],
];

$planiraniPriliv = 0;
foreach ($analitikaPriliva as $a) { $planiraniPriliv += $a[2] * $a[3] * $a[4]; }
$dodatniPrilivi = 0;
$planiraniOdlivi = 0;
foreach ($stavke as $s) {
    $godisnje = period_to_yearly($s['period'] ?? 'godisnje', $s['iznos'] ?? 0);
    if (($s['tip'] ?? '') === 'priliv') { $dodatniPrilivi += $godisnje; }
    if (($s['tip'] ?? '') === 'odliv') { $planiraniOdlivi += $godisnje; }
}
$planiraniPriliv += $dodatniPrilivi;
$ocekivaniPriliv = $planiraniPriliv * ($p('stepen_naplate', 100) / 100);
$nepredvidjeni = $planiraniOdlivi * ($p('nepredvidjeni_proc', 0) / 100);
$ukupniOdlivi = $planiraniOdlivi + $nepredvidjeni;
$rezultat = $pocetnoStanje + $ocekivaniPriliv - $ukupniOdlivi;
$saldoPlana = $ocekivaniPriliv - $ukupniOdlivi;
$brutoSaldoPlana = $planiraniPriliv - $ukupniOdlivi;
$procenatOdliva = $ocekivaniPriliv > 0 ? min(100, ($ukupniOdlivi / $ocekivaniPriliv) * 100) : 0;

$startGodine = new DateTime($godina . '-01-01');
$krajGodine = new DateTime($godina . '-12-31');
$danas = new DateTime('today');
if ((int)$danas->format('Y') < $godina) { $procenatGodine = 0; }
elseif ((int)$danas->format('Y') > $godina) { $procenatGodine = 1; }
else {
    $daniUkupno = (int)$startGodine->diff($krajGodine)->days + 1;
    $daniProslo = (int)$startGodine->diff($danas)->days + 1;
    $procenatGodine = max(0, min(1, $daniProslo / $daniUkupno));
}
$planiranoStanjeDanas = $pocetnoStanje + (($ocekivaniPriliv - $ukupniOdlivi) * $procenatGodine);

require __DIR__ . '/../includes/header.php';
?>
<?php if (!$z): ?>
    <div class="empty">Stambena zajednica nije pronađena.</div>
<?php else: ?>
<section class="finance-hero">
    <div class="finance-hero-main">
        <span class="eyebrow">Finansijski plan <?= e($godina) ?></span>
        <h2><?= e($z['naziv'] ?? 'Stambena zajednica') ?></h2>
        <p>Plan se zasniva na parametrima zgrade, planiranom stepenu naplate i planiranim odlivima. Koriste se termini priliv i odliv.</p>
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
    <div class="card stat"><span class="muted">Planirano stanje danas</span><strong><?= money_rs($planiranoStanjeDanas) ?></strong></div>
    <div class="card stat"><span class="muted">Očekivani priliv</span><strong><?= money_rs($ocekivaniPriliv) ?></strong></div>
    <div class="card stat"><span class="muted">Planirani odlivi</span><strong><?= money_rs($ukupniOdlivi) ?></strong></div>
    <div class="card stat <?= $saldoPlana >= 0 ? 'stat-good' : 'stat-bad' ?>"><span class="muted">Saldo plana</span><strong><?= money_rs($saldoPlana) ?></strong></div>
</section>

<section class="card finance-summary-card" style="margin-top:18px">
    <div class="toolbar">
        <h2>Zbir finansijskog plana</h2>
        <div class="actions"><a class="btn btn-warning btn-sm" href="index.php?page=finansijski_plan_rebalans&sz_id=<?= $szId ?>&godina=<?= $godina ?>">↻ Rebalans</a><span class="badge">prilivi / odlivi / saldo</span></div>
    </div>
    <div class="finance-summary-grid">
        <div>
            <span class="summary-label">Planirani prilivi</span>
            <strong><?= money_rs($planiraniPriliv) ?></strong>
            <small>pre stepena naplate</small>
        </div>
        <div>
            <span class="summary-label">Očekivani priliv</span>
            <strong><?= money_rs($ocekivaniPriliv) ?></strong>
            <small><?= e($p('stepen_naplate',100)) ?>% naplate</small>
        </div>
        <div>
            <span class="summary-label">Planirani odlivi</span>
            <strong><?= money_rs($ukupniOdlivi) ?></strong>
            <small>sa nepredviđenim</small>
        </div>
        <div class="<?= $saldoPlana >= 0 ? 'positive' : 'negative' ?>">
            <span class="summary-label">Saldo plana</span>
            <strong><?= money_rs($saldoPlana) ?></strong>
            <small>priliv − odlivi</small>
        </div>
        <div class="<?= $rezultat >= 0 ? 'positive' : 'negative' ?>">
            <span class="summary-label">Stanje na kraju</span>
            <strong><?= money_rs($rezultat) ?></strong>
            <small>po planu</small>
        </div>
    </div>
    <div class="plan-bar" title="Odnos planiranih odliva i očekivanog priliva">
        <span style="width: <?= e(number_format($procenatOdliva, 2, '.', '')) ?>%"></span>
    </div>
</section>
<?php
$rebalansi = db_all($conn, "SELECT * FROM finansijski_plan_rebalansi WHERE plan_id=? ORDER BY datum DESC, id DESC", 'i', [(int)$plan['id']]);
?>
<section class="card" style="margin-top:18px">
    <div class="toolbar"><h2>Rebalansi finansijskog plana</h2><a class="btn btn-warning" href="index.php?page=finansijski_plan_rebalans&sz_id=<?= $szId ?>&godina=<?= $godina ?>">↻ Novi rebalans</a></div>
    <?php if (!$rebalansi): ?>
        <div class="empty">Za ovu godinu još nije evidentiran rebalans.</div>
    <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Datum</th><th>Razlog / napomena</th></tr></thead><tbody>
        <?php foreach($rebalansi as $r): ?>
            <tr><td><strong><?= e(date('d.m.Y.', strtotime($r['datum']))) ?></strong></td><td><?= nl2br(e($r['razlog'] ?? '')) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>

<section class="grid grid-2" style="margin-top:18px">
    <div class="card">
        <div class="toolbar"><h2>Parametri plana</h2><span class="badge">obračun</span></div>
        <form method="post" class="form-grid compact-form">
            <input type="hidden" name="action" value="update_plan">
            <div class="field"><label>Tekuće održavanje / poseban deo</label><input type="number" step="0.01" name="tekuce_po_delu" value="<?= e($p('tekuce_po_delu')) ?>"></div>
            <div class="field"><label>Upravljanje / poseban deo</label><input type="number" step="0.01" name="upravljanje_po_delu" value="<?= e($p('upravljanje_po_delu')) ?>"></div>
            <div class="field"><label>Tekuće održavanje garaža / mesto</label><input type="number" step="0.01" name="garaza_po_mestu" value="<?= e($p('garaza_po_mestu')) ?>"></div>
            <div class="field"><label>Investiciono održavanje / m²</label><input type="number" step="0.01" name="investiciono_po_m2" value="<?= e($p('investiciono_po_m2')) ?>"></div>
            <div class="field"><label>Planirani stepen naplate (%)</label><input type="number" step="0.01" name="stepen_naplate" value="<?= e($p('stepen_naplate',100)) ?>"></div>
            <div class="field"><label>Nepredviđeni troškovi (%)</label><input type="number" step="0.01" name="nepredvidjeni_proc" value="<?= e($p('nepredvidjeni_proc')) ?>"></div>
            <div class="field full"><label>Napomena</label><textarea name="napomena" rows="2"><?= e($plan['napomena'] ?? '') ?></textarea></div>
            <div class="full actions"><button class="btn btn-primary" type="submit">💾 Sačuvaj parametre</button></div>
        </form>
    </div>

    <div class="card">
        <div class="toolbar"><h2>Osnova obračuna</h2><span class="badge">iz kartice zgrade</span></div>
        <div class="mini-metrics">
            <div><span>Posebni delovi</span><strong><?= e($brojDelova) ?></strong></div>
            <div><span>Garažna mesta</span><strong><?= e($brojGaraza) ?></strong></div>
            <div><span>Površina posebnih delova</span><strong><?= e($povrsinaDelova) ?> m²</strong></div>
            <div><span>Površina garaža</span><strong><?= e($povrsinaGaraza) ?> m²</strong></div>
        </div>
        <div class="result-box">
            <span>Planirani odlivi</span><strong><?= money_rs($planiraniOdlivi) ?></strong>
            <span>Nepredviđeno</span><strong><?= money_rs($nepredvidjeni) ?></strong>
            <span>Ukupno odlivi</span><strong><?= money_rs($ukupniOdlivi) ?></strong>
        </div>
    </div>
</section>

<section class="card" style="margin-top:18px">
    <div class="toolbar"><h2>Analitika planiranih priliva</h2><a class="btn btn-primary" href="index.php?page=finansijski_plan_stavka&sz_id=<?= $szId ?>&godina=<?= $godina ?>&tip=priliv">+ Dodaj planirani priliv</a></div>
    <div class="table-wrap"><table><thead><tr><th>Osnov</th><th>Obračun</th><th>Godišnje</th></tr></thead><tbody>
        <?php foreach($analitikaPriliva as $a): $god = $a[2]*$a[3]*$a[4]; ?>
        <tr><td><strong><?= e($a[0]) ?></strong><div class="muted"><?= e($a[1]) ?></div></td><td><?= e($a[2]) ?> × <?= money_rs($a[3]) ?> × <?= e($a[4]) ?> meseci</td><td><strong><?= money_rs($god) ?></strong></td></tr>
        <?php endforeach; ?>
        <?php foreach($stavke as $s): if(($s['tip'] ?? '') !== 'priliv') continue; ?>
        <tr><td><strong><?= e($s['naziv']) ?></strong><div class="muted"><?= e($s['grupa'] ?? 'Dodatno') ?></div></td><td><?= e($s['period']) ?></td><td><strong><?= money_rs(period_to_yearly($s['period'], $s['iznos'])) ?></strong><div class="actions row-actions"><a class="btn btn-light btn-sm" href="index.php?page=finansijski_plan_stavka&id=<?= (int)$s['id'] ?>&sz_id=<?= $szId ?>&godina=<?= $godina ?>">Izmeni</a><a class="btn btn-danger btn-sm" onclick="return confirm('Ukloniti ovu stavku?')" href="index.php?page=finansijski_plan_obrisi&id=<?= (int)$s['id'] ?>&sz_id=<?= $szId ?>&godina=<?= $godina ?>">Ukloni</a></div></td></tr>
        <?php endforeach; ?>
        <tr class="summary-row"><td colspan="2">Ukupno planirani prilivi</td><td><strong><?= money_rs($planiraniPriliv) ?></strong></td></tr>
        <tr class="summary-row"><td colspan="2">Očekivani priliv po stepenu naplate (<?= e($p('stepen_naplate',100)) ?>%)</td><td><strong><?= money_rs($ocekivaniPriliv) ?></strong></td></tr>
    </tbody></table></div>
</section>

<section class="card" style="margin-top:18px">
    <div class="toolbar"><h2>Planirani odlivi</h2><a class="btn btn-primary" href="index.php?page=finansijski_plan_stavka&sz_id=<?= $szId ?>&godina=<?= $godina ?>&tip=odliv">+ Dodaj planirani odliv</a></div>
    <?php if (!$stavke): ?><div class="empty">Nema planiranih odliva.</div><?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Naziv</th><th>Grupa</th><th>Period</th><th>Iznos</th><th>Godišnje</th><th>Akcija</th></tr></thead><tbody>
        <?php foreach($stavke as $s): if(($s['tip'] ?? '') !== 'odliv') continue; ?>
        <tr>
            <td><strong><?= e($s['naziv']) ?></strong><?= (int)$s['predefinisana'] ? '<div class="muted">Predefinisana stavka</div>' : '' ?></td>
            <td><?= e($s['grupa'] ?? '') ?></td>
            <td><?= e($s['period']) ?></td>
            <td><?= money_rs($s['iznos']) ?></td>
            <td><strong><?= money_rs(period_to_yearly($s['period'], $s['iznos'])) ?></strong></td>
            <td><div class="actions"><a class="btn btn-light btn-sm" href="index.php?page=finansijski_plan_stavka&id=<?= (int)$s['id'] ?>&sz_id=<?= $szId ?>&godina=<?= $godina ?>">Izmeni</a><a class="btn btn-danger btn-sm" onclick="return confirm('Ukloniti ovu stavku iz finansijskog plana?')" href="index.php?page=finansijski_plan_obrisi&id=<?= (int)$s['id'] ?>&sz_id=<?= $szId ?>&godina=<?= $godina ?>">Ukloni</a></div></td>
        </tr>
        <?php endforeach; ?>
        <tr class="summary-row"><td colspan="4">Nepredviđeni troškovi (<?= e($p('nepredvidjeni_proc')) ?>%)</td><td><strong><?= money_rs($nepredvidjeni) ?></strong></td><td></td></tr>
        <tr class="summary-row total"><td colspan="4">Ukupno planirani odlivi</td><td><strong><?= money_rs($ukupniOdlivi) ?></strong></td><td></td></tr>
        <tr class="summary-row <?= $saldoPlana >= 0 ? 'positive' : 'negative' ?>"><td colspan="4">Saldo plana</td><td><strong><?= money_rs($saldoPlana) ?></strong></td><td></td></tr>
    </tbody></table></div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
