<?php
ensure_finansijski_plan_schema($conn);
$szId = get_int('sz_id');
$godina = isset($_GET['godina']) ? (int)$_GET['godina'] : current_year();
$id = get_int('id');
$z = db_one($conn, "SELECT * FROM stambene_zajednice WHERE id=?", 'i', [$szId]);
$plan = $z ? get_or_create_finansijski_plan($conn, $szId, $godina) : null;
$stavka = ($id && $plan) ? db_one($conn, "SELECT * FROM finansijski_plan_stavke WHERE id=? AND plan_id=?", 'ii', [$id, (int)$plan['id']]) : null;
$tip = $stavka['tip'] ?? ($_GET['tip'] ?? 'odliv');
if (!in_array($tip, ['priliv','odliv'], true)) { $tip = 'odliv'; }
$title = $id ? 'Izmena stavke' : 'Nova stavka';
$subtitle = $z ? (($tip === 'priliv' ? 'Stavka planiranog priliva' : 'Stavka planiranog odliva') . ' — ' . ($z['naziv'] ?? '') . ', ' . $godina) : 'Stambena zajednica nije pronađena.';

if ($z && $plan && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $naziv = trim(post_value('naziv'));
    $grupa = trim(post_value('grupa'));
    $period = post_value('period', 'mesecno');
    if (!in_array($period, ['mesecno','godisnje','jednokratno'], true)) { $period = 'mesecno'; }
    $osnov = post_value('osnov', 'fiksno');
    if (!in_array($osnov, ['fiksno','poseban_deo','garazno_mesto','m2_posebni','m2_garaza','m2_ukupno'], true)) { $osnov = 'fiksno'; }
    $iznos = (float)str_replace(',', '.', post_value('iznos', 0));
    $mesecOd = normalize_month(post_value('mesec_od', 1), 1);
    $mesecDo = normalize_month(post_value('mesec_do', 12), 12);
    if ($period === 'jednokratno' || $period === 'godisnje') { $mesecDo = $mesecOd; }
    if ($period === 'mesecno' && $mesecDo < $mesecOd) { $mesecDo = $mesecOd; }
    $napomena = trim(post_value('napomena', ''));
    $tipPost = post_value('tip', $tip);
    if (!in_array($tipPost, ['priliv','odliv'], true)) { $tipPost = 'odliv'; }

    if ($naziv !== '') {
        if ($id && $stavka) {
            $stmt = $conn->prepare("UPDATE finansijski_plan_stavke SET tip=?, naziv=?, grupa=?, period=?, osnov=?, iznos=?, mesec_od=?, mesec_do=?, napomena=? WHERE id=? AND plan_id=?");
            $stmt->bind_param('sssssdiisii', $tipPost, $naziv, $grupa, $period, $osnov, $iznos, $mesecOd, $mesecDo, $napomena, $id, $plan['id']);
            $stmt->execute();
        } else {
            $predef = 0;
            $izvor = 'ručno';
            $stmt = $conn->prepare("INSERT INTO finansijski_plan_stavke (plan_id, tip, naziv, grupa, period, osnov, iznos, mesec_od, mesec_do, napomena, predefinisana, izvor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isssssdiisis', $plan['id'], $tipPost, $naziv, $grupa, $period, $osnov, $iznos, $mesecOd, $mesecDo, $napomena, $predef, $izvor);
            $stmt->execute();
        }
        redirect_to("index.php?page=finansijski_plan&sz_id=$szId&godina=$godina");
    }
}
$summary = ($z && $plan) ? finansijski_plan_summary($conn, $szId, $godina) : null;
$metrics = $summary['metrics'] ?? [];
require __DIR__ . '/../includes/header.php';
?>
<?php if (!$z || !$plan): ?>
    <div class="empty">Stambena zajednica nije pronađena.</div>
<?php else:
    $currentPeriod = $stavka['period'] ?? 'mesecno';
    $currentOsnov = $stavka['osnov'] ?? 'fiksno';
    $currentOd = normalize_month($stavka['mesec_od'] ?? 1, 1);
    $currentDo = normalize_month($stavka['mesec_do'] ?? 12, 12);
?>
<section class="card narrow-card">
    <div class="toolbar"><h2><?= $id ? 'Izmeni stavku' : 'Dodaj stavku' ?></h2><a class="btn btn-light" href="index.php?page=finansijski_plan&sz_id=<?= $szId ?>&godina=<?= $godina ?>">← Nazad</a></div>
    <form method="post" class="form-grid">
        <div class="field"><label>Tip</label><select name="tip"><option value="priliv" <?= $tip==='priliv'?'selected':'' ?>>Planirani priliv</option><option value="odliv" <?= $tip==='odliv'?'selected':'' ?>>Planirani odliv</option></select></div>
        <div class="field"><label>Period</label><select name="period"><option value="mesecno" <?= $currentPeriod==='mesecno'?'selected':'' ?>>Mesečno</option><option value="jednokratno" <?= $currentPeriod==='jednokratno'?'selected':'' ?>>Jednokratno</option><option value="godisnje" <?= $currentPeriod==='godisnje'?'selected':'' ?>>Godišnje</option></select></div>
        <div class="field"><label>Naziv stavke</label><input name="naziv" required value="<?= e($stavka['naziv'] ?? '') ?>" placeholder="npr. Održavanje lifta, zakup krova, prodaja otpada"></div>
        <div class="field"><label>Grupa</label><input name="grupa" value="<?= e($stavka['grupa'] ?? '') ?>" placeholder="npr. Dodatno zaduženje, zakup, oprema"></div>
        <div class="field"><label>Osnov obračuna</label><select name="osnov">
            <option value="fiksno" <?= $currentOsnov==='fiksno'?'selected':'' ?>>Fiksno</option>
            <option value="poseban_deo" <?= $currentOsnov==='poseban_deo'?'selected':'' ?>>Po posebnom delu</option>
            <option value="garazno_mesto" <?= $currentOsnov==='garazno_mesto'?'selected':'' ?>>Po garažnom mestu</option>
            <option value="m2_posebni" <?= $currentOsnov==='m2_posebni'?'selected':'' ?>>Po m² posebnih delova</option>
            <option value="m2_garaza" <?= $currentOsnov==='m2_garaza'?'selected':'' ?>>Po m² garaža</option>
            <option value="m2_ukupno" <?= $currentOsnov==='m2_ukupno'?'selected':'' ?>>Po ukupnoj m²</option>
        </select></div>
        <div class="field"><label>Iznos u dinarima</label><input type="number" step="0.01" name="iznos" value="<?= e($stavka['iznos'] ?? 0) ?>"></div>
        <div class="field"><label>Mesec početka / obračuna</label><select name="mesec_od"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $currentOd===$m?'selected':'' ?>><?= e(mesec_naziv($m)) ?></option><?php endfor; ?></select></div>
        <div class="field"><label>Mesec prestanka</label><select name="mesec_do"><?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $currentDo===$m?'selected':'' ?>><?= e(mesec_naziv($m)) ?></option><?php endfor; ?></select></div>
        <div class="field full"><label>Trenutni godišnji efekat</label><input disabled value="<?= $stavka ? e(money_rs(stavka_total($stavka, $metrics))) : 'računa se posle čuvanja' ?>"></div>
        <div class="field full"><label>Napomena</label><textarea name="napomena" rows="3"><?= e($stavka['napomena'] ?? '') ?></textarea></div>
        <div class="notice full">Za mesečnu stavku koristi se period od meseca početka do meseca prestanka. Za jednokratnu i godišnju stavku računa se samo mesec početka.</div>
        <div class="full actions"><button class="btn btn-primary" type="submit">💾 Sačuvaj</button><a class="btn btn-light" href="index.php?page=finansijski_plan&sz_id=<?= $szId ?>&godina=<?= $godina ?>">Odustani</a></div>
    </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
