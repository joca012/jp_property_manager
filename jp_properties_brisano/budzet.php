<?php
include "config.php";

$sz_id = (int)($_GET['sz_id'] ?? $_POST['sz_id'] ?? 0);

$result = $conn->query("SELECT * FROM stambene_zajednice WHERE id = $sz_id");
if (!$result || $result->num_rows == 0) {
    die("Stambena zajednica nije pronađena.");
}
$sz = $result->fetch_assoc();

$godina = date("Y");
$budzet = null;

$resBudzet = $conn->query("
    SELECT *
    FROM budzeti
    WHERE sz_id = $sz_id
    AND godina = $godina
    AND status = 'aktivan'
    LIMIT 1
");

if ($resBudzet && $resBudzet->num_rows > 0) {
    $budzet = $resBudzet->fetch_assoc();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $godina = (int)$_POST['godina'];
    $naziv = $_POST['naziv'];
    $datum_vazenja = $_POST['datum_vazenja'];
    $tekuce_po_posebnom_delu = (float)$_POST['tekuce_po_posebnom_delu'];
    $investiciono_po_m2 = (float)$_POST['investiciono_po_m2'];
    $profesionalni_upravnik_po_posebnom_delu = (float)$_POST['profesionalni_upravnik_po_posebnom_delu'];
    $garazno_mesto_mesecno = (float)$_POST['garazno_mesto_mesecno'];
    $pocetno_stanje_racuna = (float)$_POST['pocetno_stanje_racuna'];
    $nenaplacena_potrazivanja = (float)$_POST['nenaplacena_potrazivanja'];
    $procenat_naplate_potrazivanja = (float)$_POST['procenat_naplate_potrazivanja'];
    $procenat_naplate = (float)$_POST['procenat_naplate'];
    $bankarski_troskovi_mesecno = (float)$_POST['bankarski_troskovi_mesecno'];
    $nepredvidjeni_troskovi_godisnje = (float)$_POST['nepredvidjeni_troskovi_godisnje'];

    if ($budzet) {
        $stmt = $conn->prepare("
            UPDATE budzeti
            SET naziv = ?, datum_vazenja = ?, tekuce_po_posebnom_delu = ?, investiciono_po_m2 = ?,
                profesionalni_upravnik_po_posebnom_delu = ?, garazno_mesto_mesecno = ?,
                pocetno_stanje_racuna = ?, nenaplacena_potrazivanja = ?,
                procenat_naplate_potrazivanja = ?, procenat_naplate = ?,
                bankarski_troskovi_mesecno = ?, nepredvidjeni_troskovi_godisnje = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            "ssddddddddddi",
            $naziv, $datum_vazenja, $tekuce_po_posebnom_delu, $investiciono_po_m2,
            $profesionalni_upravnik_po_posebnom_delu, $garazno_mesto_mesecno,
            $pocetno_stanje_racuna, $nenaplacena_potrazivanja,
            $procenat_naplate_potrazivanja, $procenat_naplate,
            $bankarski_troskovi_mesecno, $nepredvidjeni_troskovi_godisnje,
            $budzet['id']
        );
    } else {
        $stmt = $conn->prepare("
            INSERT INTO budzeti
            (sz_id, godina, naziv, datum_vazenja, tekuce_po_posebnom_delu, investiciono_po_m2,
             profesionalni_upravnik_po_posebnom_delu, garazno_mesto_mesecno, pocetno_stanje_racuna,
             nenaplacena_potrazivanja, procenat_naplate_potrazivanja, procenat_naplate,
             bankarski_troskovi_mesecno, nepredvidjeni_troskovi_godisnje)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iissdddddddddd",
            $sz_id, $godina, $naziv, $datum_vazenja, $tekuce_po_posebnom_delu, $investiciono_po_m2,
            $profesionalni_upravnik_po_posebnom_delu, $garazno_mesto_mesecno,
            $pocetno_stanje_racuna, $nenaplacena_potrazivanja,
            $procenat_naplate_potrazivanja, $procenat_naplate,
            $bankarski_troskovi_mesecno, $nepredvidjeni_troskovi_godisnje
        );
    }

    $stmt->execute();
    header("Location: budzet.php?sz_id=" . $sz_id);
    exit;
}

function val($budzet, $field, $default = 0) {
    return $budzet ? $budzet[$field] : $default;
}

$tekuce_mesecno = $sz['broj_posebnih_delova'] * val($budzet, 'tekuce_po_posebnom_delu');
$investiciono_mesecno = $sz['povrsina_posebnih_delova'] * val($budzet, 'investiciono_po_m2');
$upravnik_mesecno = $sz['broj_posebnih_delova'] * val($budzet, 'profesionalni_upravnik_po_posebnom_delu');
$garaze_mesecno = $sz['broj_garaznih_mesta'] * val($budzet, 'garazno_mesto_mesecno');
$ukupno_mesecno = $tekuce_mesecno + $investiciono_mesecno + $upravnik_mesecno + $garaze_mesecno;
$ukupno_godisnje = $ukupno_mesecno * 12;
$ocekivana_naplata = $ukupno_godisnje * (val($budzet, 'procenat_naplate', 100) / 100);
$ocekivana_naplata_potrazivanja = val($budzet, 'nenaplacena_potrazivanja') * (val($budzet, 'procenat_naplate_potrazivanja') / 100);
$bankarski_godisnje = val($budzet, 'bankarski_troskovi_mesecno') * 12;
$dodatni_prilivi = 0;
$dodatni_odlivi = 0;
$dodatne_stavke = [];

if ($budzet) {
    $resStavke = $conn->query("
        SELECT *
        FROM budzet_stavke
        WHERE budzet_id = {$budzet['id']}
        AND aktivna = 1
        ORDER BY naziv
    ");

    if ($resStavke) {
        while ($stavka = $resStavke->fetch_assoc()) {
            $osnovica = 1;
            if ($stavka['obracun'] == 'po_posebnom_delu') {
                $osnovica = (int)$sz['broj_posebnih_delova'];
            }
            if ($stavka['obracun'] == 'po_m2') {
                $osnovica = (float)$sz['povrsina_posebnih_delova'];
            }
            if ($stavka['obracun'] == 'po_garaznom_mestu') {
                $osnovica = (int)$sz['broj_garaznih_mesta'];
            }

            $mnozilac = 1;
            if ($stavka['ucestalost'] == 'mesecno') {
                $mnozilac = 12;
            }

            $godisnji_iznos = (float)$stavka['iznos'] * $osnovica * $mnozilac;
            $stavka['godisnji_iznos'] = $godisnji_iznos;
            $dodatne_stavke[] = $stavka;

            if ($stavka['vrsta'] == 'priliv') {
                $dodatni_prilivi += $godisnji_iznos;
            } else {
                $dodatni_odlivi += $godisnji_iznos;
            }
        }
    }
}

$raspolozivo = val($budzet, 'pocetno_stanje_racuna')
    + $ocekivana_naplata
    + $ocekivana_naplata_potrazivanja
    + $dodatni_prilivi
    - $dodatni_odlivi
    - $bankarski_godisnje
    - val($budzet, 'nepredvidjeni_troskovi_godisnje');
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Budžet - <?= htmlspecialchars($sz['naziv']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include "header.php"; ?>

<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1>Budžet</h1>
        <?php if($budzet): ?>
            <a class="btn" href="budzet_stavke.php?budzet_id=<?= $budzet['id'] ?>">Stavke budžeta</a>
        <?php endif; ?>
    </div>

    <p>
        <strong><?= htmlspecialchars($sz['naziv']) ?></strong><br>
        <?= htmlspecialchars($sz['adresa']) ?>
    </p>

    <div class="grid-2">
        <form method="POST">
            <input type="hidden" name="sz_id" value="<?= $sz_id ?>">

            <label>Godina</label>
            <input type="number" name="godina" value="<?= $budzet ? $budzet['godina'] : date('Y') ?>">

            <label>Naziv budžeta</label>
            <input type="text" name="naziv" value="<?= $budzet ? htmlspecialchars($budzet['naziv']) : 'Osnovni plan' ?>">

            <label>Datum važenja</label>
            <input type="date" name="datum_vazenja" value="<?= $budzet ? $budzet['datum_vazenja'] : date('Y-01-01') ?>">

            <label>Tekuće održavanje po posebnom delu mesečno</label>
            <input type="number" step="0.01" name="tekuce_po_posebnom_delu" value="<?= val($budzet, 'tekuce_po_posebnom_delu') ?>">

            <label>Investiciono održavanje po m² mesečno</label>
            <input type="number" step="0.01" name="investiciono_po_m2" value="<?= val($budzet, 'investiciono_po_m2') ?>">

            <label>Profesionalni upravnik po posebnom delu mesečno</label>
            <input type="number" step="0.01" name="profesionalni_upravnik_po_posebnom_delu" value="<?= val($budzet, 'profesionalni_upravnik_po_posebnom_delu') ?>">

            <label>Garažno mesto mesečno</label>
            <input type="number" step="0.01" name="garazno_mesto_mesecno" value="<?= val($budzet, 'garazno_mesto_mesecno') ?>">

            <label>Početno stanje računa</label>
            <input type="number" step="0.01" name="pocetno_stanje_racuna" value="<?= val($budzet, 'pocetno_stanje_racuna') ?>">

            <label>Nenaplaćena potraživanja</label>
            <input type="number" step="0.01" name="nenaplacena_potrazivanja" value="<?= val($budzet, 'nenaplacena_potrazivanja') ?>">

            <label>Očekivana naplata potraživanja (%)</label>
            <input type="number" step="0.01" name="procenat_naplate_potrazivanja" value="<?= val($budzet, 'procenat_naplate_potrazivanja') ?>">

            <label>Očekivani procenat naplate tekućih zaduženja</label>
            <input type="number" step="0.01" name="procenat_naplate" value="<?= val($budzet, 'procenat_naplate', 100) ?>">

            <label>Bankarski troškovi mesečno</label>
            <input type="number" step="0.01" name="bankarski_troskovi_mesecno" value="<?= val($budzet, 'bankarski_troskovi_mesecno') ?>">

            <label>Nepredviđeni troškovi godišnje</label>
            <input type="number" step="0.01" name="nepredvidjeni_troskovi_godisnje" value="<?= val($budzet, 'nepredvidjeni_troskovi_godisnje') ?>">

            <button type="submit">Sačuvaj budžet</button>
        </form>

        <div class="summary-card">
            <h2>Obračun</h2>
            <p>Tekuće mesečno: <strong><?= number_format($tekuce_mesecno, 2, ',', '.') ?> RSD</strong></p>
            <p>Investiciono mesečno: <strong><?= number_format($investiciono_mesecno, 2, ',', '.') ?> RSD</strong></p>
            <p>Profesionalni upravnik mesečno: <strong><?= number_format($upravnik_mesecno, 2, ',', '.') ?> RSD</strong></p>
            <p>Garaže mesečno: <strong><?= number_format($garaze_mesecno, 2, ',', '.') ?> RSD</strong></p>
            <hr>
            <p>Ukupno mesečno: <strong><?= number_format($ukupno_mesecno, 2, ',', '.') ?> RSD</strong></p>
            <p>Ukupno godišnje: <strong><?= number_format($ukupno_godisnje, 2, ',', '.') ?> RSD</strong></p>
            <p>Očekivana naplata tekućih zaduženja: <strong><?= number_format($ocekivana_naplata, 2, ',', '.') ?> RSD</strong></p>
            <hr>
            <p>Početno stanje računa: <strong><?= number_format(val($budzet, 'pocetno_stanje_racuna'), 2, ',', '.') ?> RSD</strong></p>
            <p>Nenaplaćena potraživanja: <strong><?= number_format(val($budzet, 'nenaplacena_potrazivanja'), 2, ',', '.') ?> RSD</strong></p>
            <p>Očekivana naplata potraživanja: <strong><?= number_format($ocekivana_naplata_potrazivanja, 2, ',', '.') ?> RSD</strong></p>
            <hr>
            <p>Bankarski troškovi godišnje: <strong><?= number_format($bankarski_godisnje, 2, ',', '.') ?> RSD</strong></p>
            <p>Nepredviđeni troškovi: <strong><?= number_format(val($budzet, 'nepredvidjeni_troskovi_godisnje'), 2, ',', '.') ?> RSD</strong></p>
            <hr>

            <h2>Dodatne stavke budžeta</h2>
            <?php if (!empty($dodatne_stavke)): ?>
                <table>
                    <tr>
                        <th>Naziv</th>
                        <th>Vrsta</th>
                        <th>Godišnje</th>
                    </tr>
                    <?php foreach ($dodatne_stavke as $stavka): ?>
                        <tr>
                            <td><?= htmlspecialchars($stavka['naziv']) ?></td>
                            <td><?= htmlspecialchars($stavka['vrsta']) ?></td>
                            <td><strong><?= number_format($stavka['godisnji_iznos'], 2, ',', '.') ?> RSD</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Nema dodatnih stavki budžeta.</p>
            <?php endif; ?>

            <p>Dodatni prilivi godišnje: <strong><?= number_format($dodatni_prilivi, 2, ',', '.') ?> RSD</strong></p>
            <p>Dodatni odlivi godišnje: <strong><?= number_format($dodatni_odlivi, 2, ',', '.') ?> RSD</strong></p>

            <h2>Raspoloživo za program</h2>
            <p class="big-number"><?= number_format($raspolozivo, 2, ',', '.') ?> RSD</p>
        </div>
    </div>
</div>

</body>
</html>
