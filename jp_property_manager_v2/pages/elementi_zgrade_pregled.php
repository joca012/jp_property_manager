<?php
$szId = get_int('sz_id');

$title = 'Pregled elemenata zgrade';
$subtitle = 'Pregled evidentiranih elemenata zgrade bez uređivanja.';

if ($szId <= 0) {
    die('Nije izabrana stambena zajednica.');
}

$elementi = db_all(
    $conn,
    "SELECT oz.*, se.naziv AS element_naziv, se.kategorija, se.koristi_kolicinu
     FROM oprema_zgrade oz
     JOIN sifarnik_elemenata se ON se.id = oz.element_id
     WHERE oz.sz_id=? AND oz.aktivna=1
     ORDER BY se.kategorija, se.naziv",
    'i',
    [$szId]
);

require __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <div class="toolbar">
        <h2>Pregled elemenata zgrade</h2>

        <div class="actions">
            <a class="btn btn-light" href="index.php?page=elementi_zgrade&sz_id=<?= $szId ?>">Uredi popis</a>
            <button class="btn btn-primary" type="button" onclick="window.print()">Štampaj</button>
        </div>
    </div>

    <?php if (!$elementi): ?>
        <div class="empty">Još nema evidentiranih elemenata za ovu zgradu.</div>
    <?php else: ?>
        <?php
        $trenutnaKategorija = null;
        $otvorenaTabela = false;

        foreach ($elementi as $el):
            if ($trenutnaKategorija !== $el['kategorija']):
                if ($otvorenaTabela) {
                    echo '</tbody></table>';
                }

                $trenutnaKategorija = $el['kategorija'];
                $otvorenaTabela = true;
        ?>
                <h3 style="margin-top:25px;"><?= e($trenutnaKategorija) ?></h3>

                <table class="table" style="width:100%; border-collapse:collapse; table-layout:fixed;">
                    <colgroup>
                        <col style="width:50%;">
                        <col style="width:120px;">
                        <col>
                    </colgroup>
                    <thead>
                        <tr>
                            <th style="text-align:left; padding:12px 14px;">Element</th>
                            <th style="text-align:center; padding:12px 14px;">Količina</th>
                            <th style="text-align:left; padding:12px 14px;">Napomena / opis</th>
                        </tr>
                    </thead>
                    <tbody>
            <?php endif; ?>

            <tr>
                <td style="padding:12px 14px;"><?= e($el['element_naziv']) ?></td>
                <td style="padding:12px 14px; text-align:center; white-space:nowrap;">
                    <?php if ((int)($el['koristi_kolicinu'] ?? 1) === 1): ?>
                        <?= e($el['kolicina'] ?? 1) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td style="padding:12px 14px;"><?= e($el['napomena'] ?? '') ?></td>
            </tr>

        <?php endforeach; ?>

        <?php if ($otvorenaTabela): ?>
            </tbody></table>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
