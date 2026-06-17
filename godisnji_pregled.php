<?php
include "config.php";
include "functions.php";

autoUpdateStatus($conn);

$godina = $_GET['godina'] ?? date('Y');

$prethodnaGodina = $godina - 1;
$sledecaGodina = $godina + 1;

$prviDan = "$godina-01-01";
$poslednjiDan = "$godina-12-31";

$sql = "
    SELECT *
    FROM tasks
    WHERE status != 'obrisano'
    AND datum BETWEEN '$prviDan' AND '$poslednjiDan'
    ORDER BY datum, vreme
";

$result = $conn->query($sql);

$tasksByDate = [];

while ($row = $result->fetch_assoc()) {
    $tasksByDate[$row['datum']][] = $row;
}

function nazivMesecaGodisnji($mesec) {
    $meseci = [
        1 => 'Januar',
        2 => 'Februar',
        3 => 'Mart',
        4 => 'April',
        5 => 'Maj',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Avgust',
        9 => 'Septembar',
        10 => 'Oktobar',
        11 => 'Novembar',
        12 => 'Decembar'
    ];

    return $meseci[$mesec];
}

function skraceniDanGodisnji($datum) {
    $dani = [
        'Monday' => 'Pon',
        'Tuesday' => 'Uto',
        'Wednesday' => 'Sre',
        'Thursday' => 'Čet',
        'Friday' => 'Pet',
        'Saturday' => 'Sub',
        'Sunday' => 'Ned'
    ];

    return $dani[date('l', strtotime($datum))];
}

function bojaDana($tasks) {
    if (empty($tasks)) {
        return '#ffffff';
    }

    $imaPropusteno = false;
    $imaUToku = false;
    $ukupnoMinuta = 0;

    foreach ($tasks as $task) {
        if ($task['status'] == 'propusteno') {
            $imaPropusteno = true;
        }

        if (isTaskInProgress($task)) {
            $imaUToku = true;
        }

        if ($task['status'] != 'todo') {
            $ukupnoMinuta += (int)$task['trajanje'];
        }
    }

    if ($imaPropusteno) {
        return '#dc3545';
    }

    if ($imaUToku) {
        return '#ffc107';
    }

    if ($ukupnoMinuta >= 600) {
        return '#6f42c1';
    }

    if ($ukupnoMinuta >= 300) {
        return '#0d6efd';
    }

    if ($ukupnoMinuta > 0) {
        return '#20c997';
    }

    return '#ffffff';
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Godišnji pregled</title>

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
    padding: 10px 15px;
    background: #555;
    border-radius: 5px;
}

.container {
    padding: 20px;
}

.nav {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.nav a {
    text-decoration: none;
    background: #444;
    color: white;
    padding: 8px 12px;
    border-radius: 5px;
}

.year-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
}

.month-box {
    background: white;
    border-radius: 8px;
    padding: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.month-title {
    font-weight: bold;
    margin-bottom: 8px;
    text-align: center;
    background: #333;
    color: white;
    padding: 8px;
    border-radius: 5px;
}

.days-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}

.day-name {
    text-align: center;
    font-size: 11px;
    font-weight: bold;
    color: #555;
}

.day-cell {
    min-height: 34px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 11px;
    padding: 3px;
    box-sizing: border-box;
    cursor: default;
    position: relative;
}

.day-cell.has-tasks {
    color: white;
    border-color: transparent;
}

.day-cell.prosao-dan {
    background: #f8d7da !important;
    color: #666 !important;
    border-color: #e5bfc4 !important;
}

.day-cell a {
    color: inherit;
    text-decoration: none;
    display: block;
    height: 100%;
}

.empty {
    background: transparent;
    border: none;
}

.legend {
    background: white;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    gap: 5px;
    align-items: center;
    font-size: 13px;
}

.legend-color {
    width: 18px;
    height: 18px;
    border-radius: 4px;
    border: 1px solid #ccc;
}

.tooltip {
    display: none;
    position: absolute;
    z-index: 999;
    background: #222;
    color: white;
    padding: 8px;
    border-radius: 5px;
    width: 220px;
    font-size: 12px;
    top: 36px;
    left: 0;
}

.day-cell:hover .tooltip {
    display: block;
}

.blink {
    animation: blink 1s infinite;
}

@keyframes blink {
    0% { opacity: 1; }
    50% { opacity: 0.25; }
    100% { opacity: 1; }
}
</style>
</head>

<body>

<div class="header">
    <a href="index.php">← Kontrolna tabla</a>
    <a href="calendar.php?view=week&datum=<?= date('Y-m-d') ?>">Nedeljni</a>
    <a href="mesecni_pregled.php?datum=<?= date('Y-m-d') ?>">Mesečni</a>
    <a href="godisnji_pregled.php?godina=<?= $godina ?>">Godišnji</a>
</div>

<div class="container">

<div class="nav">
    <a href="godisnji_pregled.php?godina=<?= $prethodnaGodina ?>">← Prethodna godina</a>
    <a href="godisnji_pregled.php?godina=<?= date('Y') ?>">Ova godina</a>
    <a href="godisnji_pregled.php?godina=<?= $sledecaGodina ?>">Sledeća godina →</a>
</div>

<h2>Godišnji pregled: <?= $godina ?></h2>

<div class="legend">
    <div class="legend-item">
        <span class="legend-color" style="background:#ffffff;"></span>
        Slobodno
    </div>

    <div class="legend-item">
        <span class="legend-color" style="background:#20c997;"></span>
        Malo zauzeto
    </div>

    <div class="legend-item">
        <span class="legend-color" style="background:#0d6efd;"></span>
        Srednje zauzeto
    </div>

    <div class="legend-item">
        <span class="legend-color" style="background:#6f42c1;"></span>
        Veoma zauzeto
    </div>

    <div class="legend-item">
        <span class="legend-color" style="background:#ffc107;"></span>
        U toku
    </div>

    <div class="legend-item">
        <span class="legend-color" style="background:#dc3545;"></span>
        Propušteno
    </div>
</div>

<div class="year-grid">

<?php for ($mesec = 1; $mesec <= 12; $mesec++): ?>

    <?php
    $mesecStr = str_pad($mesec, 2, '0', STR_PAD_LEFT);
    $prviDanMeseca = "$godina-$mesecStr-01";
    $brojDana = date('t', strtotime($prviDanMeseca));
    $prviDanUNedelji = date('N', strtotime($prviDanMeseca));
    ?>

    <div class="month-box">
        <div class="month-title">
            <?= nazivMesecaGodisnji($mesec) ?>
        </div>

        <div class="days-grid">
            <?php foreach (['Pon', 'Uto', 'Sre', 'Čet', 'Pet', 'Sub', 'Ned'] as $danNaziv): ?>
                <div class="day-name"><?= $danNaziv ?></div>
            <?php endforeach; ?>

            <?php for ($i = 1; $i < $prviDanUNedelji; $i++): ?>
                <div class="day-cell empty"></div>
            <?php endfor; ?>

            <?php for ($dan = 1; $dan <= $brojDana; $dan++): ?>
                <?php
                $datumDana = "$godina-$mesecStr-" . str_pad($dan, 2, '0', STR_PAD_LEFT);
                $tasks = $tasksByDate[$datumDana] ?? [];
                $boja = bojaDana($tasks);
                $hasTasks = !empty($tasks);

                $blink = false;
                $brojObaveza = 0;
                $brojPropustenih = 0;
                $ukupnoMinuta = 0;

                foreach ($tasks as $task) {
                    if (isTaskInProgress($task)) {
                        $blink = true;
                    }

                    if ($task['status'] == 'propusteno') {
                        $brojPropustenih++;
                    }

                    if ($task['status'] != 'todo') {
                        $brojObaveza++;
                        $ukupnoMinuta += (int)$task['trajanje'];
                    }
                }

                $blinkClass = $blink ? " blink" : "";
                $hasTasksClass = $hasTasks ? " has-tasks" : "";
                $prosaoDan = strtotime($datumDana . ' 23:59:59') < time();
                $prosaoDanClass = $prosaoDan ? ' prosao-dan' : '';
                ?>

                <div class="day-cell<?= $hasTasksClass ?><?= $blinkClass ?><?= $prosaoDanClass ?>"
                     style="background:<?= $boja ?>;">
                    <a href="calendar.php?view=week&datum=<?= $datumDana ?>">
                        <?= $dan ?>
                    </a>

                    <?php if ($hasTasks): ?>
                        <div class="tooltip">
                            <b><?= date('d.m.Y.', strtotime($datumDana)) ?></b><br>
                            <?= skraceniDanGodisnji($datumDana) ?><br><br>
                            Obaveza: <?= $brojObaveza ?><br>
                            Zauzeto: <?= round($ukupnoMinuta / 60, 1) ?> h<br>
                            <?php if ($brojPropustenih > 0): ?>
                                Propušteno: <?= $brojPropustenih ?><br>
                            <?php endif; ?>

                            <br>
                            <?php foreach ($tasks as $task): ?>
                                <?php if (!empty($task['vreme'])): ?>
                                    <?= date('H:i', strtotime($task['vreme'])) ?>
                                <?php endif; ?>
                                <?= htmlspecialchars($task['opis1']) ?><br>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endfor; ?>
        </div>
    </div>

<?php endfor; ?>

</div>

</div>

</body>
</html>