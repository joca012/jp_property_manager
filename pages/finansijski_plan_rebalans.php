<?php
$szId = get_int('sz_id');
$godina = isset($_GET['godina']) ? (int)$_GET['godina'] : current_year();
$summary = finansijski_plan_summary($conn, $szId, $godina);
$z = $summary['zgrada'] ?? null;
$plan = $summary['plan'] ?? null;
$title = 'Rebalans finansijskog plana';
$subtitle = $z ? (($z['naziv'] ?? '') . ' — ' . $godina . '. godina') : 'Stambena zajednica nije pronađena.';

if ($z && $plan && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $datum = post_value('datum', date('Y-m-d'));
    $razlog = trim(post_value('razlog', ''));
    $snapshot = json_encode([
        'planirani_priliv' => $summary['planiraniPriliv'],
        'ocekivani_priliv' => $summary['ocekivaniPriliv'],
        'planirani_odlivi' => $summary['ukupniOdlivi'],
        'saldo_plana' => $summary['saldoPlana'],
        'ocekivano_stanje_kraj_godine' => $summary['ocekivanoKrajGodine'],
        'planirano_stanje_danas' => $summary['planiranoStanjeDanas'],
    ], JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare("INSERT INTO finansijski_plan_rebalansi (plan_id, datum, razlog, snapshot_json) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isss', $plan['id'], $datum, $razlog, $snapshot);
    $stmt->execute();
    redirect_to("index.php?page=finansijski_plan&sz_id=$szId&godina=$godina");
}

require __DIR__ . '/../includes/header.php';
?>
<?php if (!$z): ?>
    <div class="empty">Stambena zajednica nije pronađena.</div>
<?php else: ?>
<section class="card narrow-card">
    <div class="toolbar">
        <h2>↻ Evidentiraj rebalans</h2>
        <a class="btn btn-light" href="index.php?page=finansijski_plan&sz_id=<?= $szId ?>&godina=<?= $godina ?>">← Nazad</a>
    </div>
    <p class="muted">Rebalans ovde predstavlja zabeleženu izmenu finansijskog plana u toku godine. Aplikacija čuva trenutni zbir plana kao trag, a zatim možeš menjati parametre, prilive i odlive.</p>
    <div class="finance-summary-grid compact-summary" style="margin:18px 0">
        <div><span class="summary-label">Očekivani priliv</span><strong><?= money_rs($summary['ocekivaniPriliv']) ?></strong></div>
        <div><span class="summary-label">Planirani odlivi</span><strong><?= money_rs($summary['ukupniOdlivi']) ?></strong></div>
        <div class="<?= $summary['saldoPlana'] >= 0 ? 'positive' : 'negative' ?>"><span class="summary-label">Saldo plana</span><strong><?= money_rs($summary['saldoPlana']) ?></strong></div>
    </div>
    <form method="post" class="form-grid">
        <div class="field"><label>Datum rebalansa</label><input type="date" name="datum" value="<?= date('Y-m-d') ?>"></div>
        <div class="field full"><label>Razlog / napomena</label><textarea name="razlog" rows="5" placeholder="Npr. promena planiranih radova, nova ponuda, veći vanredni odlivi, promena stepena naplate..."></textarea></div>
        <div class="full actions"><button class="btn btn-warning" type="submit">Sačuvaj rebalans</button></div>
    </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
