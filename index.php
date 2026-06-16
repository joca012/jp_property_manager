<?php
include "config.php";
include "functions.php";

autoUpdateStatus($conn);

/* =========================
   FILTER
========================= */
$kategorija = $_GET['kategorija'] ?? 'SVE';
$today = date('Y-m-d');

/* =========================
   DNEVNI RASPORED
========================= */
$sqlToday = "SELECT * FROM tasks 
             WHERE datum = '$today'
             AND status != 'obrisano'
             ORDER BY vreme ASC";

$resultToday = $conn->query($sqlToday);

/* =========================
   GLAVNI UPIT
========================= */
if ($kategorija == "SVE") {
    $sql = "SELECT * FROM tasks 
            WHERE status != 'obrisano'
            ORDER BY datum, vreme";
} else {
    $sql = "SELECT * FROM tasks 
            WHERE kategorija = '$kategorija'
            AND status != 'obrisano'
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

.left { background: white; }
.right { background: #ddd; }

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

<!-- ========================= HEADER ========================= -->
<div class="header">

    <a href="index.php?kategorija=SVE">SVE</a>
    <a href="?kategorija=JA">JA</a>
    <a href="?kategorija=EPS">EPS</a>
    <a href="?kategorija=PIDRA">PIDRA</a>
    <a href="?kategorija=PLAC">PLAC</a>
    <a href="?kategorija=SAFE_LIFE">SAFE LIFE</a>
	
    <a href="generisi_smene.php">Generiši smene</a>
    <a class="todo" href="todo.php">TODO</a>
    <a class="trash" href="recycle.php" title="Obrisano">🗑</a>

</div>

<div class="container">

<!-- ========================= LEFT - DNEVNI RASPORED ========================= -->
<div class="left">

<h2 style="display:flex;justify-content:space-between;align-items:center;">
    Dnevni raspored
    <span style="font-size:14px;color:#666;">
        <?php echo date("d.m.Y."); ?>
    </span>
</h2>

<?php
if ($resultToday && $resultToday->num_rows > 0) {

    while ($row = $resultToday->fetch_assoc()) {

        $datumFormat = date("d.m.Y.", strtotime($row['datum']));
        $vremeFormat = date("H:i", strtotime($row['vreme']));
        $statusColor = getStatusColor($row['status']);
        $dugme = renderActions($row);

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

<!-- ========================= RIGHT - LISTA PO KATEGORIJI ========================= -->
<div class="right">

<h2>Kategorija: <?php echo $kategorija; ?></h2>

<?php
if ($result && $result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {

        if ($row['status'] == "todo" || !$row['datum']) {
            $datumFormat = "🔴 Datum: ⚠️";
            $vremeFormat = "🔴 Vreme: ⚠️";
        } else {
            $datumFormat = date("d.m.Y.", strtotime($row['datum']));
            $vremeFormat = date("H:i", strtotime($row['vreme']));
        }

        $statusColor = getStatusColor($row['status']);
        $dugme = renderActions($row);

        echo "
        <div class='card'>

            <div style='display:flex;justify-content:space-between;align-items:center;'>
                <b>$datumFormat $vremeFormat</b>";

        if ($kategorija == "SVE") {
            echo "
                <span class='badge' style='background:#444;'>
                    {$row['kategorija']}
                </span>";
        }

        echo "
            </div>

            <br>

            {$row['opis1']}<br>
            {$row['opis2']}<br><br>

            <span class='badge' style='background:$statusColor'>
                {$row['status']}
            </span>
        ";

        if ($row['status'] == "todo" || $row['status'] == "propusteno") {
            echo " 
            <a href='#' 
               onclick='openPlan({$row['id']}); return false;' 
               style='margin-left:10px;color:#333;'>
               ✏ Planiraj
            </a>";
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

<!-- ========================= MODAL ZA PLANIRANJE ========================= -->
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