<?php
include "config.php";
include "functions.php";

autoUpdateStatus($conn);

$datum = $_GET['datum'] ?? date('Y-m-d');
$todoKategorija = $_GET['todo_kategorija'] ?? 'SVE';
$todoKategorijaSql = $conn->real_escape_string($todoKategorija);
$todoFilterParam = '&todo_kategorija=' . urlencode($todoKategorija);

$pocetakSedmice = date('Y-m-d', strtotime('monday this week', strtotime($datum)));
$krajSedmice = date('Y-m-d', strtotime($pocetakSedmice . ' +6 days'));

$prethodnaSedmica = date('Y-m-d', strtotime($pocetakSedmice . ' -7 days'));
$sledecaSedmica = date('Y-m-d', strtotime($pocetakSedmice . ' +7 days'));

$dani = [];
for ($i = 0; $i < 7; $i++) {
    $dani[] = date('Y-m-d', strtotime($pocetakSedmice . " +$i days"));
}

$startHour = 06;
$endHour = 24;
$hourHeight = 55;
$slotMinutes = 30;
$slotHeight = $hourHeight / 2;

/*
   Uzimamo sve obaveze koje se PREKLAPAJU sa prikazanom sedmicom.
   Ovo rešava višednevne obaveze koje počnu u prethodnoj sedmici,
   ali se nastavljaju u ovoj sedmici.
*/
$sedmicaStart = $pocetakSedmice . ' 00:00:00';
$sedmicaEnd = date('Y-m-d', strtotime($pocetakSedmice . ' +7 days')) . ' 00:00:00';

$sql = "
    SELECT *
    FROM tasks
    WHERE status != 'obrisano'
    AND datum IS NOT NULL
    AND vreme IS NOT NULL
    AND CONCAT(datum, ' ', vreme) < '$sedmicaEnd'
    AND DATE_ADD(CONCAT(datum, ' ', vreme), INTERVAL IFNULL(NULLIF(trajanje, 0), 30) MINUTE) > '$sedmicaStart'
    ORDER BY datum, vreme
";

$result = $conn->query($sql);

$tasksByDate = [];

while ($row = $result->fetch_assoc()) {
    if (empty($row['datum']) || empty($row['vreme'])) {
        continue;
    }

    $start = strtotime($row['datum'] . ' ' . $row['vreme']);
    $trajanje = (int)$row['trajanje'];

    if ($trajanje <= 0) {
        $trajanje = 30;
    }

    $end = $start + ($trajanje * 60);

    foreach ($dani as $dan) {
$dayStart = strtotime($dan . ' ' . str_pad($startHour, 2, '0', STR_PAD_LEFT) . ':00:00');
$dayEnd = strtotime($dan . ' 23:59:59');

        if ($start <= $dayEnd && $end >= $dayStart) {
            $segment = $row;
            $segment['segment_start'] = max($start, $dayStart);
            $segment['segment_end'] = min($end, $dayEnd);

            $tasksByDate[$dan][] = $segment;
        }
    }
}

if ($todoKategorija == 'SVE') {
    $sqlTodo = "
        SELECT *
        FROM tasks
        WHERE status = 'todo'
        ORDER BY created_at DESC
    ";
} else {
    $sqlTodo = "
        SELECT *
        FROM tasks
        WHERE status = 'todo'
        AND kategorija = '$todoKategorijaSql'
        ORDER BY created_at DESC
    ";
}

$resultTodo = $conn->query($sqlTodo);

if (!function_exists('srpskiDan')) {
    function srpskiDan($datum) {
        $dani = [
            'Monday' => 'Ponedeljak',
            'Tuesday' => 'Utorak',
            'Wednesday' => 'Sreda',
            'Thursday' => 'Četvrtak',
            'Friday' => 'Petak',
            'Saturday' => 'Subota',
            'Sunday' => 'Nedelja'
        ];

        return $dani[date('l', strtotime($datum))];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Kalendar</title>

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
    display: flex;
    height: calc(100vh - 70px);
}

.todo-panel {
    width: 270px;
    background: white;
    padding: 15px;
    overflow-y: auto;
    border-right: 1px solid #ccc;
}

.calendar {
    flex: 1;
    overflow: auto;
    padding: 15px;
}

.todo-card {
    background: #fff3cd;
    padding: 10px;
    margin-bottom: 10px;
    border-left: 5px solid #ffc107;
    border-radius: 5px;
    cursor: grab;
}

.todo-filter {
    margin-bottom: 15px;
}

.todo-filter select {
    width: 100%;
    padding: 8px;
    border-radius: 5px;
    border: 1px solid #ccc;
}

.nav {
    margin-bottom: 15px;
    display: flex;
    gap: 10px;
}

.nav a {
    text-decoration: none;
    background: #444;
    color: white;
    padding: 8px 12px;
    border-radius: 5px;
}

.week-calendar {
    display: grid;
    grid-template-columns: 70px repeat(7, minmax(140px, 1fr));
    background: white;
    border: 1px solid #ccc;
}

.time-header,
.day-header {
    height: 45px;
    background: #333;
    color: white;
    border: 1px solid #444;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    text-align: center;
}

.day-header.prosao-dan-kalendar {
    background: #f8d7da;
    color: #333;
    border-color: #e6aeb7;
}

.time-column {
    position: relative;
    height: <?= ($endHour - $startHour) * $hourHeight ?>px;
    background: #eee;
    border-right: 1px solid #ccc;
}

.time-label {
    position: absolute;
    left: 0;
    width: 100%;
    height: <?= $hourHeight ?>px;
    border-top: 1px solid #ccc;
    font-size: 12px;
    text-align: center;
    padding-top: 2px;
    box-sizing: border-box;
}

.day-column {
    position: relative;
    height: <?= ($endHour - $startHour) * $hourHeight ?>px;
    border-right: 1px solid #ddd;
    background:
        repeating-linear-gradient(
            to bottom,
            #ffffff 0px,
            #ffffff <?= $slotHeight - 1 ?>px,
            #f1f1f1 <?= $slotHeight ?>px,
            #ffffff <?= $slotHeight + 1 ?>px,
            #ffffff <?= $hourHeight - 1 ?>px,
            #dcdcdc <?= $hourHeight ?>px
        );
}

.day-column.prosao-dan-kalendar {
    background:
        repeating-linear-gradient(
            to bottom,
            #f8d7da 0px,
            #f8d7da <?= $slotHeight - 1 ?>px,
            #efc8cf <?= $slotHeight ?>px,
            #f8d7da <?= $slotHeight + 1 ?>px,
            #f8d7da <?= $hourHeight - 1 ?>px,
            #e6aeb7 <?= $hourHeight ?>px
        );
}

.calendar-slot {
    position: absolute;
    left: 0;
    right: 0;
    height: <?= $slotHeight ?>px;
}

.drop-target {
    background: rgba(13, 110, 253, 0.15);
}

.task-block {
    position: absolute;
    left: 5px;
    right: 5px;
    border-radius: 6px;
    padding: 6px;
    box-sizing: border-box;
    color: white;
    font-size: 12px;
    overflow: hidden;
    z-index: 5;
    box-shadow: 0 2px 5px rgba(0,0,0,0.25);
    cursor: default;
}

.task-time {
    font-weight: bold;
    margin-bottom: 4px;
}

.task-title {
    font-weight: bold;
}

.task-status {
    font-size: 11px;
    opacity: 0.95;
    margin-top: 4px;
}

.task-actions {
    margin-top: 6px;
    font-size: 11px;
}

.task-actions a {
    color: white;
    text-decoration: underline;
    font-weight: bold;
}

.blink {
    animation: blink 1s infinite;
}

@keyframes blink {
    0% { opacity: 1; }
    50% { opacity: 0.25; }
    100% { opacity: 1; }
}
.task-block {
    transition: all 0.2s ease;
    overflow: hidden;
}

.task-block.expanded {
    z-index: 999;
    min-height: 120px !important;
    overflow: visible;
}
</style>
</head>

<body>

<div class="header">
    <a href="index.php">← Kontrolna tabla</a>
    <a href="calendar.php?view=week&datum=<?= $datum ?><?= $todoFilterParam ?>">Nedeljni</a>
	<a href="mesecni_pregled.php?datum=<?= $datum ?>">Mesečni</a>
	<a href="godisnji_pregled.php?godina=<?= date('Y', strtotime($datum)) ?>">Godišnji</a>
</div>

<div class="container">

<div class="todo-panel">
    <h3>TODO obaveze</h3>

    <form method="GET" class="todo-filter">
        <input type="hidden" name="view" value="week">
        <input type="hidden" name="datum" value="<?= htmlspecialchars($datum) ?>">

        <label for="todo_kategorija"><b>Kategorija</b></label><br>
        <select name="todo_kategorija" id="todo_kategorija" onchange="this.form.submit()">
            <?php foreach (['SVE', 'JA', 'EPS', 'PIDRA', 'PLAC', 'SAFE_LIFE'] as $kat): ?>
                <option value="<?= $kat ?>" <?= $todoKategorija == $kat ? 'selected' : '' ?>>
                    <?= $kat == 'SAFE_LIFE' ? 'SAFE LIFE' : $kat ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($resultTodo && $resultTodo->num_rows > 0): ?>
        <?php while ($todo = $resultTodo->fetch_assoc()): ?>
            <div class="todo-card" draggable="true" data-id="<?= $todo['id'] ?>">
                <b><?= htmlspecialchars($todo['opis1']) ?></b><br>
                <?= htmlspecialchars($todo['kategorija']) ?><br>
                <?= (int)$todo['trajanje'] ?> min
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>Nema TODO obaveza za izabrani filter.</p>
    <?php endif; ?>
</div>

<div class="calendar">

<div class="nav">
    <a href="calendar.php?view=week&datum=<?= $prethodnaSedmica ?><?= $todoFilterParam ?>">← Prethodna sedmica</a>
    <a href="calendar.php?view=week&datum=<?= date('Y-m-d') ?><?= $todoFilterParam ?>">Ova sedmica</a>
    <a href="calendar.php?view=week&datum=<?= $sledecaSedmica ?><?= $todoFilterParam ?>">Sledeća sedmica →</a>
</div>

<h2>
    Nedeljni kalendar:
    <?= date('d.m.Y.', strtotime($pocetakSedmice)) ?>
    -
    <?= date('d.m.Y.', strtotime($krajSedmice)) ?>
</h2>

<div class="week-calendar">

    <div class="time-header"></div>

    <?php foreach ($dani as $dan): ?>
        <?php $prosaoDanClass = (strtotime($dan . ' 23:59:59') < time()) ? ' prosao-dan-kalendar' : ''; ?>
        <div class="day-header<?= $prosaoDanClass ?>">
            <?= srpskiDan($dan) ?><br>
            <?= date('d.m.', strtotime($dan)) ?>
        </div>
    <?php endforeach; ?>

    <div class="time-column">
        <?php for ($h = $startHour; $h < $endHour; $h++): ?>
            <div class="time-label" style="top: <?= ($h - $startHour) * $hourHeight ?>px;">
                <?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00
            </div>
        <?php endfor; ?>
    </div>

    <?php foreach ($dani as $dan): ?>
        <?php $prosaoDanClass = (strtotime($dan . ' 23:59:59') < time()) ? ' prosao-dan-kalendar' : ''; ?>
        <div class="day-column<?= $prosaoDanClass ?>">

            <?php for ($h = $startHour; $h < $endHour; $h++): ?>
                <?php foreach ([0, 30] as $m): ?>
                    <?php
                    $slotTime = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ':00';
                    $slotTop = (($h - $startHour) * 60 + $m) / 60 * $hourHeight;
                    ?>
                    <div class="calendar-slot"
                         data-date="<?= $dan ?>"
                         data-time="<?= $slotTime ?>"
                         style="top: <?= $slotTop ?>px;">
                    </div>
                <?php endforeach; ?>
            <?php endfor; ?>

            <?php if (!empty($tasksByDate[$dan])): ?>
                <?php foreach ($tasksByDate[$dan] as $task): ?>
                    <?php
                    $startTimestamp = $task['segment_start'];
                    $endTimestamp = $task['segment_end'];

                    $startDayTimestamp = strtotime($dan . " " . str_pad($startHour, 2, '0', STR_PAD_LEFT) . ":00:00");

                    $minutesFromStart = ($startTimestamp - $startDayTimestamp) / 60;

                    if ($minutesFromStart < 0) {
                        $minutesFromStart = 0;
                    }

                    $top = ($minutesFromStart / 60) * $hourHeight;

                    $trajanjeSegmenta = ($endTimestamp - $startTimestamp) / 60;
                    $height = max(28, ($trajanjeSegmenta / 60) * $hourHeight);

                    $statusColor = getStatusColor($task['status']);
                    $blinkClass = isTaskInProgress($task) ? " blink" : "";

                    $vremeOd = date('H:i', $startTimestamp);
                    $vremeDo = date('H:i', $endTimestamp);
                    ?>

                    <div class="task-block<?= $blinkClass ?>"
					onclick="toggleTask(this)"
					style="
							top: <?= $top ?>px;
							height: <?= $height ?>px;
							background: <?= $statusColor ?>;
					">
                        <div class="task-time">
                            <?= $vremeOd ?> - <?= $vremeDo ?>
                        </div>

                        <div class="task-title">
                            <?= htmlspecialchars($task['opis1']) ?>
                        </div>

                        <div class="task-status">
                            <?= htmlspecialchars($task['kategorija']) ?> |
                            <?= htmlspecialchars($task['status']) ?>
                        </div>

                        <?php if ($task['status'] == 'zakazano' || $task['status'] == 'propusteno'): ?>
                            <div class="task-actions" onclick="event.stopPropagation();">
                                <a href="otkazi.php?id=<?= $task['id'] ?>&return=<?= urlencode('calendar.php?view=week&datum=' . $datum) ?>"
								style="color:white;font-size:11px;text-decoration:underline;">
								✖ Otkaži
								</a>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    <?php endforeach; ?>

</div>

</div>
</div>

<script>
let draggedTaskId = null;

document.querySelectorAll('.todo-card').forEach(card => {
    card.addEventListener('dragstart', function () {
        draggedTaskId = this.dataset.id;
    });
});

document.querySelectorAll('.calendar-slot').forEach(slot => {
    slot.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.classList.add('drop-target');
    });

    slot.addEventListener('dragleave', function () {
        this.classList.remove('drop-target');
    });

    slot.addEventListener('drop', function (e) {
        e.preventDefault();
        this.classList.remove('drop-target');

        if (!draggedTaskId) {
            return;
        }

        const datum = this.dataset.date;
        const vreme = this.dataset.time;

        fetch('update_task_time.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=' + encodeURIComponent(draggedTaskId) +
                  '&datum=' + encodeURIComponent(datum) +
                  '&vreme=' + encodeURIComponent(vreme)
        })
        .then(response => response.text())
        .then(data => {
            if (data.trim() === 'OK') {
                location.reload();
            } else {
                alert(data);
            }
        });
    });
});

function toggleTask(el) {

    document.querySelectorAll('.task-block.expanded').forEach(item => {
        if (item !== el) {
            item.classList.remove('expanded');
        }
    });

    el.classList.toggle('expanded');
}
</script>

</body>
</html>