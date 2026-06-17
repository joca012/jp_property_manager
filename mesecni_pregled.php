<?php
include "config.php";
include "functions.php";

autoUpdateStatus($conn);

$datum = $_GET['datum'] ?? date('Y-m-d');

$godina = date('Y', strtotime($datum));
$mesec = date('m', strtotime($datum));

$prviDan = "$godina-$mesec-01";
$poslednjiDan = date('Y-m-t', strtotime($prviDan));

$prethodniMesec = date('Y-m-d', strtotime($prviDan . ' -1 month'));
$sledeciMesec = date('Y-m-d', strtotime($prviDan . ' +1 month'));

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

function nazivMeseca($mesec) {
    $meseci = [
        '01' => 'Januar',
        '02' => 'Februar',
        '03' => 'Mart',
        '04' => 'April',
        '05' => 'Maj',
        '06' => 'Jun',
        '07' => 'Jul',
        '08' => 'Avgust',
        '09' => 'Septembar',
        '10' => 'Oktobar',
        '11' => 'Novembar',
        '12' => 'Decembar'
    ];

    return $meseci[$mesec];
}

function skraceniDan($datum) {
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

$brojDana = date('t', strtotime($prviDan));
$prviDanUNedelji = date('N', strtotime($prviDan));
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Mesečni pregled</title>

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

.month-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
}

.day-name {
    background: #333;
    color: white;
    padding: 10px;
    text-align: center;
    font-weight: bold;
    border-radius: 5px;
}

.day {
    background: white;
    min-height: 140px;
    padding: 10px;
    border-radius: 6px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.empty {
    background: transparent;
    box-shadow: none;
}

.day-number {
    font-weight: bold;
    margin-bottom: 8px;
}

.task-line {
    font-size: 12px;
    margin-bottom: 4px;
    padding: 4px;
    border-radius: 4px;
    color: white;
}

.summary {
    font-size: 12px;
    margin-bottom: 8px;
    background: #eee;
    padding: 5px;
    border-radius: 4px;
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
    <a href="calendar.php?view=week&datum=<?= $datum ?>">Nedeljni</a>
    <a href="mesecni_pregled.php?datum=<?= $datum ?>">Mesečni</a>
    <a href="godisnji_pregled.php?godina=<?= $godina ?>">Godišnji</a>
</div>

<div class="container">

<div class="nav">
    <a href="mesecni_pregled.php?datum=<?= $prethodniMesec ?>">← Prethodni mesec</a>
    <a href="mesecni_pregled.php?datum=<?= date('Y-m-d') ?>">Ovaj mesec</a>
    <a href="mesecni_pregled.php?datum=<?= $sledeciMesec ?>">Sledeći mesec →</a>
</div>

<h2><?= nazivMeseca($mesec) ?> <?= $godina ?></h2>

<div class="month-grid">

<?php
$naziviDana = ['Pon', 'Uto', 'Sre', 'Čet', 'Pet', 'Sub', 'Ned'];

foreach ($naziviDana as $dan) {
    echo "<div class='day-name'>$dan</div>";
}

for ($i = 1; $i < $prviDanUNedelji; $i++) {
    echo "<div class='day empty'></div>";
}

for ($dan = 1; $dan <= $brojDana; $dan++) {
    $datumDana = "$godina-$mesec-" . str_pad($dan, 2, '0', STR_PAD_LEFT);

    $brojObaveza = 0;
    $brojPropustenih = 0;
    $ukupnoMinuta = 0;

    if (!empty($tasksByDate[$datumDana])) {
        foreach ($tasksByDate[$datumDana] as $task) {
            if ($task['status'] != 'todo') {
                $brojObaveza++;
                $ukupnoMinuta += (int)$task['trajanje'];
            }

            if ($task['status'] == 'propusteno') {
                $brojPropustenih++;
            }
        }
    }

    echo "<div class='day'>";
    echo "<div class='day-number'>$dan. " . skraceniDan($datumDana) . "</div>";

    if ($brojObaveza > 0) {
        echo "<div class='summary'>";
        echo "$brojObaveza obaveza<br>";
        echo "Zauzeto: " . round($ukupnoMinuta / 60, 1) . " h";

        if ($brojPropustenih > 0) {
            echo "<br>⚠ Propušteno: $brojPropustenih";
        }

        echo "</div>";
    }

    if (!empty($tasksByDate[$datumDana])) {
        foreach ($tasksByDate[$datumDana] as $task) {
            if (empty($task['vreme'])) {
                continue;
            }

            $statusColor = getStatusColor($task['status']);
            $blinkClass = isTaskInProgress($task) ? " blink" : "";

            echo "
                <div class='task-line$blinkClass' style='background:$statusColor'>
                    " . date('H:i', strtotime($task['vreme'])) . "
                    " . htmlspecialchars($task['opis1']) . "
                </div>
            ";
        }
    } else {
        echo "<span style='font-size:12px;color:#777;'>Slobodno</span>";
    }

    echo "</div>";
}
?>

</div>

</div>

</body>
</html>