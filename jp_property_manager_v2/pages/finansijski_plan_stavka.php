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
$subtitle = $z ? (($tip === 'priliv' ? 'Planirani priliv' : 'Planirani odliv') . ' — ' . ($z['naziv'] ?? '') . ', ' . $godina) : 'Stambena zajednica nije pronađena.';

if ($z && $plan && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $naziv = trim(post_value('naziv'));
    $grupa = trim(post_value('grupa'));
    $period = post_value('period', 'godisnje');
    if (!in_array($period, ['mesecno','godisnje','jednokratno'], true)) { $period = 'godisnje'; }
    $iznos = (float)str_replace(',', '.', post_value('iznos', 0));
    $napomena = trim(post_value('napomena', ''));
    $tipPost = post_value('tip', $tip);
    if (!in_array($tipPost, ['priliv','odliv'], true)) { $tipPost = 'odliv'; }

    if ($naziv !== '') {
        if ($id && $stavka) {
            $stmt = $conn->prepare("UPDATE finansijski_plan_stavke SET tip=?, naziv=?, grupa=?, period=?, iznos=?, napomena=? WHERE id=? AND plan_id=?");
            $stmt->bind_param('ssssdsii', $tipPost, $naziv, $grupa, $period, $iznos, $napomena, $id, $plan['id']);
            $stmt->execute();
        } else {
            $predef = 0;
            $stmt = $conn->prepare("INSERT INTO finansijski_plan_stavke (plan_id, tip, naziv, grupa, period, iznos, napomena, predefinisana) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssdsi', $plan['id'], $tipPost, $naziv, $grupa, $period, $iznos, $napomena, $predef);
            $stmt->execute();
        }
        redirect_to("index.php?page=finansijski_plan&sz_id=$szId&godina=$godina");
    }
}
require __DIR__ . '/../includes/header.php';
?>
<?php if (!$z || !$plan): ?>
    <div class="empty">Stambena zajednica nije pronađena.</div>
<?php else: ?>
<section class="card narrow-card">
    <div class="toolbar"><h2><?= $id ? 'Izmeni stavku' : 'Dodaj stavku' ?></h2><a class="btn btn-light" href="index.php?page=finansijski_plan&sz_id=<?= $szId ?>&godina=<?= $godina ?>">← Nazad</a></div>
    <form method="post" class="form-grid">
        <div class="field"><label>Tip</label><select name="tip"><option value="priliv" <?= $tip==='priliv'?'selected':'' ?>>Planirani priliv</option><option value="odliv" <?= $tip==='odliv'?'selected':'' ?>>Planirani odliv</option></select></div>
        <div class="field"><label>Period</label><select name="period"><option value="mesecno" <?= (($stavka['period'] ?? '')==='mesecno')?'selected':'' ?>>Mesečno</option><option value="godisnje" <?= (($stavka['period'] ?? 'godisnje')==='godisnje')?'selected':'' ?>>Godišnje</option><option value="jednokratno" <?= (($stavka['period'] ?? '')==='jednokratno')?'selected':'' ?>>Jednokratno</option></select></div>
        <div class="field"><label>Naziv stavke</label><input name="naziv" required value="<?= e($stavka['naziv'] ?? '') ?>" placeholder="npr. Osiguranje, popravka krova, ostali priliv"></div>
        <div class="field"><label>Grupa</label><input name="grupa" value="<?= e($stavka['grupa'] ?? '') ?>" placeholder="npr. Oprema, Tekuće održavanje, Ostalo"></div>
        <div class="field"><label>Iznos u dinarima</label><input type="number" step="0.01" name="iznos" value="<?= e($stavka['iznos'] ?? 0) ?>"></div>
        <div class="field"><label>Godišnji efekat</label><input disabled value="<?= $stavka ? e(money_rs(period_to_yearly($stavka['period'], $stavka['iznos']))) : 'računa se posle čuvanja' ?>"></div>
        <div class="field full"><label>Napomena</label><textarea name="napomena" rows="3"><?= e($stavka['napomena'] ?? '') ?></textarea></div>
        <div class="full actions"><button class="btn btn-primary" type="submit">💾 Sačuvaj</button><a class="btn btn-light" href="index.php?page=finansijski_plan&sz_id=<?= $szId ?>&godina=<?= $godina ?>">Odustani</a></div>
    </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
