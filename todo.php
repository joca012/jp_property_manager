<?php

include "config.php";

/* =========================
   UČITAJ ŠABLONE
========================= */
$sabloni = $conn->query("SELECT * FROM sabloni ORDER BY tip, naziv");

/* =========================
   DODAVANJE NOVOG TODO
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['id'])) {

    $kategorija = $_POST['kategorija'];
    $opis1 = $_POST['opis1'];
    $opis2 = $_POST['opis2'];
    $trajanje = (int)$_POST['trajanje'];
    $sablon_id = !empty($_POST['sablon_id']) ? (int)$_POST['sablon_id'] : "NULL";

    $sql = "INSERT INTO tasks
            (kategorija, opis1, opis2, trajanje, status, sablon_id)
            VALUES
            (
                '$kategorija',
                '$opis1',
                '$opis2',
                $trajanje,
                'todo',
                $sablon_id
            )";

    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:green'>Obaveza dodata u TODO!</p>";
    } else {
        echo "Greška: " . $conn->error;
    }
}

?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<title>TODO</title>

<style>

body {
    font-family: Arial;
    padding:20px;
}

input, textarea, select {
    width:300px;
    padding:8px;
    margin:5px;
}

button {
    padding:10px 20px;
}

.todo-item {
    border:1px solid #ccc;
    padding:10px;
    margin-top:10px;
}

</style>

</head>

<body>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">

    <h2 style="margin:0;">Nova TODO obaveza</h2>

    <div style="display:flex; gap:10px;">
        <a href="sabloni.php"
           style="
            background:#555;
            color:#fff;
            padding:10px 12px;
            border-radius:5px;
            text-decoration:none;
           ">
            Šabloni
        </a>

        <a href="index.php"
           style="
            background:#555;
            color:#fff;
            padding:10px 12px;
            border-radius:5px;
            text-decoration:none;
           ">
            ← Nazad
        </a>
    </div>

</div>

<!-- =========================
     FORMA ZA NOVU TODO OBAVEZU
========================= -->
<form method="POST">

<label>Šablon</label><br>
<select id="sablonSelect" name="sablon_id">
    <option value="">-- bez šablona --</option>

    <?php while ($s = $sabloni->fetch_assoc()) { ?>
        <option
            value="<?= $s['id'] ?>"
            data-kategorija="<?= htmlspecialchars($s['kategorija']) ?>"
            data-opis1="<?= htmlspecialchars($s['opis1']) ?>"
            data-opis2="<?= htmlspecialchars($s['opis2']) ?>"
            data-trajanje="<?= htmlspecialchars($s['trajanje']) ?>"
            data-vreme="<?= htmlspecialchars($s['vreme']) ?>"
            data-tip="<?= htmlspecialchars($s['tip']) ?>"
        >
            <?= htmlspecialchars($s['naziv']) ?> — <?= htmlspecialchars($s['tip']) ?>
        </option>
    <?php } ?>
</select>

<button type="button" onclick="primeniSablon()">
    Primeni šablon
</button>

<br><br>

<select name="kategorija" id="kategorijaInput" required>
    <option value="JA">JA</option>
    <option value="EPS">EPS</option>
    <option value="PIDRA">PIDRA</option>
    <option value="PLAC">PLAC</option>
    <option value="SAFE_LIFE">SAFE LIFE</option>
</select>

<br>

<input 
type="text"
name="opis1"
id="opis1Input"
placeholder="Kratak opis"
required
>

<br>

<textarea
name="opis2"
id="opis2Input"
placeholder="Detaljniji opis">
</textarea>

<br>

<input 
type="number"
name="trajanje"
id="trajanjeInput"
placeholder="Trajanje (minuti)"
required
>

<br>

<button type="submit">
Dodaj u TODO
</button>

</form>

<hr>

<h2>Trenutne TODO obaveze</h2>

<?php

$result = $conn->query(
"SELECT * FROM tasks 
 WHERE status='todo' OR status='propusteno'
 ORDER BY created_at DESC"
);

while($row = $result->fetch_assoc()) {

echo "

<div class='todo-item'>

<b>{$row['kategorija']}</b><br>

{$row['opis1']}<br>

{$row['opis2']}<br>

Trajanje: {$row['trajanje']} min

<br><br>

<a href='#' onclick='openPlan({$row['id']}); return false;'>
    ✏ Planiraj
</a>

</div>

";

}

?>

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
    document.getElementById("planFrame").src = "planiraj.php?id=" + id + "&from=todo";
    document.getElementById("planModal").style.display = "flex";
}

function closePlan(){
    document.getElementById("planModal").style.display = "none";
    document.getElementById("planFrame").src = "";
}

function primeniSablon(){
    const select = document.getElementById("sablonSelect");
    const option = select.options[select.selectedIndex];

    if (!option.value) {
        return;
    }

    document.getElementById("kategorijaInput").value = option.getAttribute("data-kategorija");
    document.getElementById("opis1Input").value = option.getAttribute("data-opis1");
    document.getElementById("opis2Input").value = option.getAttribute("data-opis2");
    document.getElementById("trajanjeInput").value = option.getAttribute("data-trajanje");
}
</script>

</body>

</html>