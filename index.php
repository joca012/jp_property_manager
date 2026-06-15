<?php
include "config.php";
include "functions.php";
autoUpdateStatus($conn);

/* =========================
   FILTER (DEFAULT = SVE)
========================= */
$kategorija = $_GET['kategorija'] ?? 'SVE';
$today = date('Y-m-d');

/* =========================
   DNEVNI RASPORED
========================= */
$sqlToday = "SELECT * FROM tasks 
WHERE datum = '$today'
ORDER BY vreme ASC";
$resultToday = $conn->query($sqlToday);

/* =========================
   GLAVNI UPIT (FILTER)
========================= */
if ($kategorija == "SVE") {

    $sql = "SELECT * FROM tasks ORDER BY datum, vreme";

} else {

    $sql = "SELECT * FROM tasks 
            WHERE kategorija = '$kategorija'
            ORDER BY datum, vreme";
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
</style>

</head>

<body>

<!-- =========================
     HEADER
========================= -->
<div class="header">

    <a href="index.php?kategorija=SVE">SVE</a>
    <a href="?kategorija=JA">JA</a>
    <a href="?kategorija=EPS">EPS</a>
    <a href="?kategorija=PIDRA">PIDRA</a>
    <a href="?kategorija=PLAC">PLAC</a>
    <a href="?kategorija=SAFE_LIFE">SAFE LIFE</a>

    <a class="todo" href="todo.php">TODO</a>

</div>

<div class="container">

<!-- =========================
     LEVA STRANA (DANAS)
========================= -->
<div class="left">

<h2 style="display:flex; justify-content:space-between; align-items:center;">
    Dnevni raspored
    <span style="font-size:14px; color:#666;">
        <?php echo date("d.m.Y."); ?>
    </span>
</h2>

<?php
if ($resultToday && $resultToday->num_rows > 0) {

    while($row = $resultToday->fetch_assoc()) {

        $datumFormat = date("d.m.Y.", strtotime($row['datum']));
        $vremeFormat = date("H:i", strtotime($row['vreme']));
        $statusColor = getStatusColor($row['status']);

        $dugme = "";

        if ($row['status'] != "zavrseno") {
            $dugme .= "<br><br><a href='zavrsi.php?id={$row['id']}'>✔ Završi</a>";
        }

        if ($row['status'] != "zavrseno" && $row['status'] != "todo") {
            $dugme .= " | <a href='otkazi.php?id={$row['id']}'>✖ Otkaži</a>";
        }

        echo "
        <div class='card' style='border-left:6px solid $statusColor'>

            <b>$datumFormat $vremeFormat</b> - {$row['kategorija']}<br><br>

            {$row['opis1']}<br>

            Trajanje: {$row['trajanje']} min<br><br>

            <span class='badge' style='background:$statusColor'>
                {$row['status']}
            </span>

            $dugme

        </div>
        ";
    }

} else {
    echo "Nema obaveza za danas.";
}
?>

</div>

<!-- =========================
     DESNA STRANA (FILTER)
========================= -->
<div class="right">

<h2>Kategorija: <?php echo $kategorija; ?></h2>

<?php
if ($result && $result->num_rows > 0) {

    while($row = $result->fetch_assoc()) {

        /* DATUM/VREME LOGIKA */
        if ($row['status'] == "todo" || !$row['datum']) {
            $datumFormat = "🔴 Datum: ⚠️";
            $vremeFormat = "🔴 Vreme: ⚠️";
        } else {
            $datumFormat = date("d.m.Y.", strtotime($row['datum']));
            $vremeFormat = date("H:i", strtotime($row['vreme']));
        }

        $statusColor = getStatusColor($row['status']);

        echo "
        <div class='card'>

            <b>$datumFormat $vremeFormat</b><br><br>

            {$row['opis1']}<br>
            {$row['opis2']}<br><br>

            <span class='badge' style='background:$statusColor'>
                {$row['status']}
            </span>

            ". (
    ($row['status'] == "todo" || $row['status'] == "propusteno")
    ? " <a href='todo.php?planiraj={$row['id']}' style='margin-left:10px;color:#333;'>✏ Planiraj</a>"
    : ""
) . "

        </div>
        ";
    }

} else {
    echo "Nema obaveza za ovu kategoriju.";
}
?>

</div>

</div>

</body>
</html>