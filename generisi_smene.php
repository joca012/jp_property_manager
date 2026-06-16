<?php
session_start();
include "config.php";

$poruka = "";
$greska = "";

$ciklusi = $conn->query("SELECT * FROM ciklusi ORDER BY naziv ASC");

/* =========================
   FUNKCIJA: PROVERA KONFLIKTA
========================= */
function nadjiKonflikte($conn, $datum, $vreme, $trajanje) {
    $konflikti = [];

    $datum = $conn->real_escape_string($datum);
    $vreme = $conn->real_escape_string($vreme);
    $trajanje = (int)$trajanje;

    $start = $vreme;
    $end = date("H:i:s", strtotime($vreme . " +$trajanje minutes"));

    $sql = "
        SELECT *
        FROM tasks
        WHERE datum = '$datum'
        AND status NOT IN ('obrisano', 'zavrseno', 'todo')
        AND (
            '$start' < ADDTIME(vreme, SEC_TO_TIME(trajanje * 60))
            AND
            '$end' > vreme
        )
        ORDER BY vreme ASC
    ";

    $res = $conn->query($sql);

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $konflikti[] = $row;
        }
    }

    return $konflikti;
}

/* =========================
   PREVIEW GENERISANJE
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['preview'])) {

    $ciklus_id = (int)$_POST['ciklus_id'];
    $pocetak = $_POST['pocetak'];
    $broj_dana = (int)$_POST['broj_dana'];

    unset($_SESSION['preview_smene']);

    if (!$ciklus_id || !$pocetak || !$broj_dana) {
        $greska = "Popuni sva polja.";
    } else {

        $stavke = [];

        $sql = "
            SELECT 
                cs.id,
                cs.ciklus_id,
                cs.sablon_id,
                cs.redosled,
                cs.broj_dana,
                cs.tip,
                s.naziv,
                s.opis1,
                s.opis2,
                s.vreme,
                s.trajanje
            FROM ciklus_stavke cs
            LEFT JOIN sabloni s ON s.id = cs.sablon_id
            WHERE cs.ciklus_id = $ciklus_id
            ORDER BY cs.redosled ASC
        ";

        $res = $conn->query($sql);

        if (!$res || $res->num_rows == 0) {
            $greska = "Ciklus nema stavke.";
        } else {

            while ($row = $res->fetch_assoc()) {
                $stavke[] = $row;
            }

            $prosireni_ciklus = [];

            foreach ($stavke as $stavka) {
                for ($d = 0; $d < (int)$stavka['broj_dana']; $d++) {
                    $prosireni_ciklus[] = $stavka;
                }
            }

            if (count($prosireni_ciklus) == 0) {
                $greska = "Ciklus je prazan.";
            } else {

                $preview = [];
                $duzina_ciklusa = count($prosireni_ciklus);

                for ($i = 0; $i < $broj_dana; $i++) {

                    $datum = date("Y-m-d", strtotime($pocetak . " +$i days"));
                    $stavka = $prosireni_ciklus[$i % $duzina_ciklusa];

                    if ($stavka['tip'] == 'slobodno' || empty($stavka['sablon_id'])) {
                        continue;
                    }

                    $konflikti = nadjiKonflikte(
                        $conn,
                        $datum,
                        $stavka['vreme'],
                        $stavka['trajanje']
                    );

                    $preview[] = [
                        "datum" => $datum,
                        "naziv" => $stavka['naziv'],
                        "opis1" => $stavka['opis1'],
                        "opis2" => $stavka['opis2'],
                        "vreme" => $stavka['vreme'],
                        "trajanje" => $stavka['trajanje'],
                        "konflikti" => $konflikti
                    ];
                }

                $_SESSION['preview_smene'] = $preview;
            }
        }
    }
}

/* =========================
   POTVRDA UPISA
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['potvrdi_upis'])) {

    if (empty($_SESSION['preview_smene'])) {
        $greska = "Nema podataka za upis.";
    } else {

        $upisano = 0;
        $preskoceno = 0;

        foreach ($_SESSION['preview_smene'] as $smena) {

            $datum = $conn->real_escape_string($smena['datum']);
            $vreme = $conn->real_escape_string($smena['vreme']);
            $opis1 = $conn->real_escape_string($smena['opis1']);
            $opis2 = $conn->real_escape_string($smena['opis2']);
            $trajanje = (int)$smena['trajanje'];

            // Zaštita od duplog generisanja iste smene
            $check = $conn->query("
                SELECT id 
                FROM tasks
                WHERE datum = '$datum'
                AND vreme = '$vreme'
                AND opis1 = '$opis1'
                AND kategorija = 'EPS'
                AND status != 'obrisano'
                LIMIT 1
            ");

            if ($check && $check->num_rows > 0) {
                $preskoceno++;
                continue;
            }

            $sql = "
                INSERT INTO tasks
                (kategorija, datum, vreme, opis1, opis2, trajanje, status)
                VALUES
                (
                    'EPS',
                    '$datum',
                    '$vreme',
                    '$opis1',
                    '$opis2',
                    $trajanje,
                    'zakazano'
                )
            ";

            if ($conn->query($sql)) {
                $upisano++;
            }
        }

        unset($_SESSION['preview_smene']);

        $poruka = "Upisano smena: $upisano. Preskočeno duplikata: $preskoceno.";
    }
}

$preview = $_SESSION['preview_smene'] ?? [];
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Generisanje smena</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            padding: 20px;
        }

        .box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        input, select, button {
            padding: 8px;
            margin: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }

        th {
            background: #eee;
        }

        .ok {
            color: green;
            font-weight: bold;
        }

        .konflikt {
            color: red;
            font-weight: bold;
        }

        .red-konflikt {
            background: #fff3f3;
        }

        .poruka {
            background: #d4edda;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .greska {
            background: #f8d7da;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .dugme {
            text-decoration: none;
            background: #555;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 15px;
        }

        .potvrdi {
            background: #198754;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            padding: 10px 15px;
        }
    </style>
</head>

<body>

<a class="dugme" href="index.php">← Nazad</a>

<h2>Generisanje smena</h2>

<?php if ($poruka): ?>
    <div class="poruka"><?= htmlspecialchars($poruka) ?></div>
<?php endif; ?>

<?php if ($greska): ?>
    <div class="greska"><?= htmlspecialchars($greska) ?></div>
<?php endif; ?>

<div class="box">
    <form method="post">
        <label>Ciklus:</label>

        <select name="ciklus_id" required>
            <option value="">-- izaberi ciklus --</option>

            <?php if ($ciklusi): ?>
                <?php while ($c = $ciklusi->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>">
                        <?= htmlspecialchars($c['naziv']) ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>

        <label>Početni datum:</label>
        <input type="date" name="pocetak" required>

        <label>Broj dana:</label>
        <input type="number" name="broj_dana" value="30" min="1" max="366" required>

        <button type="submit" name="preview">Prikaži preview</button>
    </form>
</div>

<?php if (!empty($preview)): ?>

    <h3>Preview generisanja</h3>

    <?php
        $broj_konflikata = 0;

        foreach ($preview as $smena) {
            if (!empty($smena['konflikti'])) {
                $broj_konflikata++;
            }
        }
    ?>

    <?php if ($broj_konflikata > 0): ?>
        <div class="greska">
            Pronađeno konfliktnih smena: <?= $broj_konflikata ?>.
            Smene će ipak biti upisane ako potvrdiš.
        </div>
    <?php else: ?>
        <div class="poruka">
            Nema konflikata u izabranom periodu.
        </div>
    <?php endif; ?>

    <table>
        <tr>
            <th>Datum</th>
            <th>Smena</th>
            <th>Vreme</th>
            <th>Trajanje</th>
            <th>Status</th>
        </tr>

        <?php foreach ($preview as $smena): ?>
            <tr class="<?= !empty($smena['konflikti']) ? 'red-konflikt' : '' ?>">
                <td><?= date("d.m.Y", strtotime($smena['datum'])) ?></td>

                <td>
                    <?= htmlspecialchars($smena['opis1']) ?><br>
                    <small><?= htmlspecialchars($smena['opis2']) ?></small>
                </td>

                <td><?= substr($smena['vreme'], 0, 5) ?></td>

                <td><?= (int)$smena['trajanje'] ?> min</td>

                <td>
                    <?php if (empty($smena['konflikti'])): ?>

                        <span class="ok">✅ Slobodno</span>

                    <?php else: ?>

                        <span class="konflikt">⚠ Konflikt</span>

                        <ul>
                            <?php foreach ($smena['konflikti'] as $k): ?>
                                <li>
                                    <?= substr($k['vreme'], 0, 5) ?>
                                    —
                                    <?= htmlspecialchars($k['opis1']) ?>

                                    <?php if (!empty($k['opis2'])): ?>
                                        <br>
                                        <small><?= htmlspecialchars($k['opis2']) ?></small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <br>

    <form method="post" onsubmit="return confirm('Potvrđuješ upis svih prikazanih smena, uključujući i one koje imaju konflikt?');">
        <button class="potvrdi" type="submit" name="potvrdi_upis">
            Potvrdi upis smena
        </button>
    </form>

<?php endif; ?>

</body>
</html>