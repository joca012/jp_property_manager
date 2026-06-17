<?php
include "config.php";
include "functions.php";

autoUpdateStatus($conn);

$datum = $_GET['datum'] ?? date('Y-m-d');

$pocetakSedmice = date('Y-m-d', strtotime('monday this week', strtotime($datum)));
$krajSedmice = date('Y-m-d', strtotime($pocetakSedmice . ' +6 days'));

$prethodnaSedmica = date('Y-m-d', strtotime($pocetakSedmice . ' -7 days'));
$sledecaSedmica = date('Y-m-d', strtotime($pocetakSedmice . ' +7 days'));

$sql = "
    SELECT *
    FROM tasks
    WHERE status != 'obrisano'
    AND datum BETWEEN '$pocetakSedmice' AND '$krajSedmice'
    ORDER BY datum, vreme
";
$result = $conn->query($sql);

$tasksByDate = [];

while ($row = $result->fetch_assoc()) {
    $tasksByDate[$row['datum']][] = $row;
}

$sqlTodo = "
    SELECT *
    FROM tasks
    WHERE status = 'todo'
    ORDER BY created_at DESC
";
$resultTodo = $conn->query($sqlTodo);

$dani = [];
for ($i = 0; $i < 7; $i++) {
    $dani[] = date('Y-m-d', strtotime($pocetakSedmice . " +$i days"));
}

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

$startHour = 6;
$endHour = 24;
$hourHeight = 60;
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
            #ffffff <?= $hourHeight - 1 ?>px,
            #e5e5e5 <?= $hourHeight ?>px
        );
}

.calendar-slot {
    position: absolute;
    left: 0;
    right: 0;
    height: <?= $hourHeight ?>px;
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
}

.task-block a {
    color: white;
    text-decoration: none;
}

.task-time {
    font-weight: bold;
    margin-bottom: 4px;
}

.task-status {
    font-size: 11px;
    opacity: 0.95;
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
    <a href="#">Mesečni</a>
    <a href="#">Godišnji</a>
</div>

<div class="container">

<div class="todo-panel">
    <h3>TODO obaveze</h3>

    <?php if ($resultTodo && $resultTodo->num_rows > 0): ?>
        <?php while ($todo = $resultTodo->fetch_assoc()): ?>
            <div class="todo-card" draggable="true" data-id="<?= $todo['id'] ?>">
                <b><?= htmlspecialchars($todo['opis1']) ?></b><br>
                <?= htmlspecialchars($todo['kategorija']) ?><br>
                <?= (int)$todo['trajanje'] ?> min
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>Nema TODO obaveza.</p>
    <?php endif; ?>
</div>

<div class="calendar">

<div class="nav">
    <a href="calendar.php?view=week&datum=<?= $prethodnaSedmica ?>">← Prethodna sedmica</a>
    <a href="calendar.php?view=week&datum=<?= date('Y-m-d') ?>">Ova sedmica</a>
    <a href="calendar.php?view=week&datum=<?= $sledecaSedmica ?>">Sledeća sedmica →</a>
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
        <div class="day-header">
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
        <div class="day-column">

            <?php for ($h = $startHour; $h < $endHour; $h++): ?>
                <div class="calendar-slot"
                     data-date="<?= $dan ?>"
                     data-time="<?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00:00"
                     style="top: <?= ($h - $startHour) * $hourHeight ?>px;">
                </div>
            <?php endfor; ?>

            <?php if (!empty($tasksByDate[$dan])): ?>
                <?php foreach ($tasksByDate[$dan] as $task): ?>
                    <?php
                    if (empty($task['vreme'])) {
                        continue;
                    }

                    $startTimestamp = strtotime($task['datum'] . " " . $task['vreme']);
                    $startDayTimestamp = strtotime($task['datum'] . " " . str_pad($startHour, 2, '0', STR_PAD_LEFT) . ":00:00");

                    $minutesFromStart = ($startTimestamp - $startDayTimestamp) / 60;

                    if ($minutesFromStart < 0) {
                        $minutesFromStart = 0;
                    }

                    $top = ($minutesFromStart / 60) * $hourHeight;

                    $trajanje = (int)$task['trajanje'];
                    if ($trajanje <= 0) {
                        $trajanje = 30;
                    }

                    $height = max(28, ($trajanje / 60) * $hourHeight);

                    $statusColor = getStatusColor($task['status']);
                    $blinkClass = isTaskInProgress($task) ? " blink" : "";

                    $vremeOd = date('H:i', strtotime($task['vreme']));
                    $vremeDo = date('H:i', strtotime("+$trajanje minutes", $startTimestamp));
                    ?>

                    <div class="task-block<?= $blinkClass ?>"
                         style="
                            top: <?= $top ?>px;
                            height: <?= $height ?>px;
                            background: <?= $statusColor ?>;
                         ">
                        <div class="task-time">
                            <?= $vremeOd ?> - <?= $vremeDo ?>
                        </div>

                        <a href="planiraj.php?id=<?= $task['id'] ?>">
                            <?= htmlspecialchars($task['opis1']) ?>
                        </a>

                        <div class="task-status">
                            <?= htmlspecialchars($task['kategorija']) ?> |
                            <?= htmlspecialchars($task['status']) ?>
                        </div>
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
</script>

</body>
</html>