<?php

include "config.php";
include "functions.php";

autoUpdateStatus($conn);

/* =========================
   FILTER
========================= */

$kategorija = $_GET['kategorija'] ?? 'SVE';
$datum = $_GET['datum'] ?? date('Y-m-d');
$sedmica = isset($_GET['sedmica']) && $_GET['sedmica'] == 1;
$slobodno = isset($_GET['slobodno']) && $_GET['slobodno'] == 1;

$kategorijaSql = $conn->real_escape_string($kategorija);
$datumSql = $conn->real_escape_string($datum);

/* =========================
   LINK HELPER
========================= */

function linkSaParametrima($kategorija, $datum, $sedmica = false, $slobodno = false) {
    $url = "index.php?kategorija=" . urlencode($kategorija) . "&datum=" . urlencode($datum);

    if ($sedmica) {
        $url .= "&sedmica=1";
    }

    if ($slobodno) {
        $url .= "&slobodno=1";
    }

    return $url;
}

/* =========================
   DATUMI ZA NAVIGACIJU
========================= */

$juce = date('Y-m-d', strtotime($datum . ' -1 day'));
$danas = date('Y-m-d');
$sutra = date('Y-m-d', strtotime($datum . ' +1 day'));

/* =========================
   DNEVNI / SEDMIČNI RASPORED
========================= */

if ($sedmica) {
    $pocetakSedmice = date('Y-m-d', strtotime('monday this week', strtotime($datum)));
    $krajSedmice = date('Y-m-d', strtotime($pocetakSedmice . ' +6 days'));

    $sqlToday = "
        SELECT *
        FROM tasks
        WHERE datum BETWEEN '$pocetakSedmice' AND '$krajSedmice'
        AND status != 'obrisano'
        ORDER BY datum ASC, vreme ASC
    ";
} else {
    $sqlToday = "
        SELECT *
        FROM tasks
        WHERE datum = '$datumSql'
        AND status != 'obrisano'
        ORDER BY vreme ASC
    ";
}

$resultToday = $conn->query($sqlToday);

/* =========================
   GLAVNI UPIT - DESNO
========================= */

if ($kategorija == "SVE") {
    $sql = "
        SELECT *
        FROM tasks
        WHERE status != 'obrisano'
        ORDER BY datum, vreme
    ";
} else {
    $sql = "
        SELECT *
        FROM tasks
        WHERE kategorija = '$kategorijaSql'
        AND status != 'obrisano'
        ORDER BY datum, vreme
    ";
}

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Task System</title>

<style>
body {
    font-family: Arial;
    margin: 0;
    background: #f2f2f2;
}

.header {
    background: #222;
    padding: 15px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.header a {
    color: white;
    text-decoration: none;
    padding: 10px 20px;
    background: #555;
    border-radius: 5px;
}

.header a:hover {
    background: #777;
}

.todo {
    margin-left: auto;
    background: red !important;
}

.trash {
    background: #6c757d !important;
    font-size: 18px;
}

.container {
    display: flex;
    height: calc(100vh - 70px);
}

.left, .right {
    width: 50%;
    padding: 20px;
    overflow-y: auto;
}

.left {
    background: white;
}

.right {
    background: #ddd;
}

.card {
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 6px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    background: #fff;
}

.badge {
    padding: 3px 8px;
    color: #fff;
    border-radius: 4px;
    font-size: 12px;
}

.blink {
    animation: blink 1s infinite;
}

@keyframes blink {
    0% { opacity: 1; }
    50% { opacity: 0.25; }
    100% { opacity: 1; }
}

.nav-datum {
    display: flex;
    gap: 8px;
    margin-bottom: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.nav-levo {
    display: flex;
    gap: 8px;
}

.nav-desno {
    margin-left: auto;
    display: flex;
    gap: 8px;
}

.nav-datum a {
    text-decoration: none;
    background: #444;
    color: white;
    padding: 8px 12px;
    border-radius: 5px;
    font-size: 14px;
}

.nav-datum a:hover {
    background: #666;
}

.datum-grupa {
    margin-top: 20px;
    margin-bottom: 10px;
    padding: 8px;
    background: #eee;
    border-radius: 5px;
    font-weight: bold;
}

.slobodan-termin {
    background: #e7f5ff;
    border-left: 6px solid #0d6efd;
    color: #084298;
}

.napomena {
    background: #fff3cd;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
}
</style>

</head>

<body>

<div class="header">
    <a href="<?= linkSaParametrima('SVE', $datum, $sedmica, $slobodno) ?>">SVE</a>
    <a href="<?= linkSaParametrima('JA', $datum, $sedmica, $slobodno) ?>">JA</a>
    <a href="<?= linkSaParametrima('EPS', $datum, $sedmica, $slobodno) ?>">EPS</a>
    <a href="<?= linkSaParametrima('PIDRA', $datum, $sedmica, $slobodno) ?>">PIDRA</a>
    <a href="<?= linkSaParametrima('PLAC', $datum, $sedmica, $slobodno) ?>">PLAC</a>
    <a href="<?= linkSaParametrima('SAFE_LIFE', $datum, $sedmica, $slobodno) ?>">SAFE LIFE</a>

    <a href="generisi_smene.php">Generiši smene</a>
    <a class="todo" href="todo.php">TODO</a>
    <a class="trash" href="recycle.php" title="Obrisano"></a>
</div>

<div class="container">

<div class="left">

<h2 style="display:flex;justify-content:space-between;align-items:center;">
    <?= $sedmica ? 'Sedmični raspored' : 'Dnevni raspored' ?>

    <span style="font-size:14px;color:#666;">
        <?php if ($sedmica): ?>
            <?= date("d.m.Y.", strtotime($pocetakSedmice)) ?>
            -
            <?= date("d.m.Y.", strtotime($krajSedmice)) ?>
        <?php else: ?>
            <?= date("d.m.Y.", strtotime($datum)) ?>
        <?php endif; ?>
    </span>
</h2>

<div class="nav-datum">
    <div class="nav-levo">
        <a href="<?= linkSaParametrima($kategorija, $juce, false, $slobodno) ?>">← Juče</a>
        <a href="<?= linkSaParametrima($kategorija, $danas, false, $slobodno) ?>">Danas</a>
        <a href="<?= linkSaParametrima($kategorija, $sutra, false, $slobodno) ?>">Sutra →</a>
    </div>

    <div class="nav-desno">
        <a href="<?= linkSaParametrima($kategorija, $datum, true, $slobodno) ?>">Prikaži celu nedelju</a>

        <?php if ($slobodno): ?>
            <a href="<?= linkSaParametrima($kategorija, $datum, $sedmica, false) ?>">Sakrij slobodno vreme</a>
        <?php else: ?>
            <a href="<?= linkSaParametrima($kategorija, $datum, $sedmica, true) ?>">Prikaži slobodno vreme</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($slobodno && $sedmica): ?>
    <div class="napomena">
        Slobodno vreme se trenutno prikazuje samo u dnevnom prikazu.
        Izaberi konkretan dan preko dugmadi Juče / Danas / Sutra.
    </div>
<?php endif; ?>

<?php

if ($resultToday && $resultToday->num_rows > 0) {
    $trenutniDatum = "";
    $prethodniKraj = strtotime($datum . " 06:00:00");
    $krajDana = strtotime($datum . " 23:59:00");

    while ($row = $resultToday->fetch_assoc()) {
        $datumFormat = date("d.m.Y.", strtotime($row['datum']));
        $vremeFormat = !empty($row['vreme']) ? date("H:i", strtotime($row['vreme'])) : "";
        $statusColor = getStatusColor($row['status']);
        $dugme = renderActions($row);

        $blinkClass = isTaskInProgress($row) ? " blink" : "";

        if ($sedmica && $trenutniDatum != $row['datum']) {
            $trenutniDatum = $row['datum'];
            echo "<div class='datum-grupa'>$datumFormat</div>";
        }

        if ($slobodno && !$sedmica && !empty($row['vreme']) && (int)$row['trajanje'] > 0) {
            $pocetakTaska = strtotime($row['datum'] . " " . $row['vreme']);
            $krajTaska = strtotime("+" . (int)$row['trajanje'] . " minutes", $pocetakTaska);

            if ($pocetakTaska > $prethodniKraj) {
                echo "
                    <div class='card slobodan-termin'>
                        <b>" . date("H:i", $prethodniKraj) . " - " . date("H:i", $pocetakTaska) . "</b><br>
                        Slobodno vreme
                    </div>
                ";
            }

            if ($krajTaska > $prethodniKraj) {
                $prethodniKraj = $krajTaska;
            }
        }

        echo "
            <div class='card' style='border-left:6px solid $statusColor'>
                <b>$datumFormat $vremeFormat</b> - {$row['kategorija']}<br><br>

                {$row['opis1']}<br>
                Trajanje: {$row['trajanje']} min<br><br>

                <span class='badge$blinkClass' style='background:$statusColor'>
                    {$row['status']}
                </span>

                $dugme
            </div>
        ";
    }

    if ($slobodno && !$sedmica && $prethodniKraj < $krajDana) {
        echo "
            <div class='card slobodan-termin'>
                <b>" . date("H:i", $prethodniKraj) . " - " . date("H:i", $krajDana) . "</b><br>
                Slobodno vreme
            </div>
        ";
    }
} else {
    if ($slobodno && !$sedmica) {
        echo "
            <div class='card slobodan-termin'>
                <b>06:00 - 23:59</b><br>
                Ceo dan je slobodan.
            </div>
        ";
    } else {
        echo $sedmica ? "Nema obaveza za ovu sedmicu." : "Nema obaveza za izabrani dan.";
    }
}

?>

</div>

<div class="right">

<h2>Kategorija: <?= htmlspecialchars($kategorija) ?></h2>

<?php

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($row['status'] == "todo" || !$row['datum']) {
            $datumFormat = "Datum: ⚠️";
            $vremeFormat = "Vreme: ⚠️";
        } else {
            $datumFormat = date("d.m.Y.", strtotime($row['datum']));
            $vremeFormat = !empty($row['vreme']) ? date("H:i", strtotime($row['vreme'])) : "";
        }

        $statusColor = getStatusColor($row['status']);
        $dugme = renderActions($row);

        $blinkClass = isTaskInProgress($row) ? " blink" : "";

        echo "
            <div class='card'>
                <div style='display:flex;justify-content:space-between;align-items:center;'>
                    <b>$datumFormat $vremeFormat</b>
        ";

        if ($kategorija == "SVE") {
            echo "
                <span class='badge' style='background:#444;'>
                    {$row['kategorija']}
                </span>
            ";
        }

        echo "
                </div>

                <br>

                {$row['opis1']}<br>
                {$row['opis2']}<br><br>

                <span class='badge$blinkClass' style='background:$statusColor'>
                    {$row['status']}
                </span>
        ";

        if ($row['status'] == "todo" || $row['status'] == "propusteno") {
            echo "
                <a href='#'
                   onclick='openPlan({$row['id']}); return false;'
                   style='margin-left:10px;color:#333;'>
                   ✏ Planiraj
                </a>
            ";
        }

        echo $dugme;

        echo "
            </div>
        ";
    }
} else {
    echo "Nema obaveza za ovu kategoriju.";
}

?>

</div>

</div>

<div id="planModal" style="
display:none;
position:fixed;
top:0;
left:0;
width:100%;
height:100%;
background:rgba(0,0,0,.6);
z-index:9999;
justify-content:center;
align-items:center;
">
    <div style="
    width:500px;
    background:white;
    border-radius:10px;
    overflow:hidden;
    ">
        <div style="
        padding:10px;
        background:#222;
        color:white;
        display:flex;
        justify-content:space-between;
        ">
            <span>Planiranje obaveze</span>

            <button onclick="closePlan()" style="background:red;color:white;">
                X
            </button>
        </div>

        <iframe id="planFrame" style="width:100%;height:400px;border:none;"></iframe>
    </div>
</div>

<script>
function openPlan(id){
    document.getElementById("planFrame").src = "planiraj.php?id=" + id + "&from=index";
    document.getElementById("planModal").style.display = "flex";
}

function closePlan(){
    document.getElementById("planModal").style.display = "none";
    document.getElementById("planFrame").src = "";
}
</script>

</body>
</html>