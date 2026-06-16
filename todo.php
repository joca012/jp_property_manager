<?php

include "config.php";

/* =========================
   PLANIRANJE (UPDATE TASKA)
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id']) && !empty($_POST['id'])) {

    $id = $_POST['id'];
    $datum = $_POST['datum'];
    $vreme = $_POST['vreme'];

    $sql = "UPDATE tasks 
            SET datum='$datum', vreme='$vreme', status='zakazano'
            WHERE id=$id";

    $conn->query($sql);

    header("Location: todo.php");
    exit;
}

/* =========================
   DODAVANJE NOVOG TODO (INSERT)
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['id'])) {

    $kategorija = $_POST['kategorija'];
    $opis1 = $_POST['opis1'];
    $opis2 = $_POST['opis2'];
    $trajanje = $_POST['trajanje'];

    $sql = "INSERT INTO tasks
    (kategorija, opis1, opis2, trajanje, status)
    VALUES
    (
    '$kategorija',
    '$opis1',
    '$opis2',
    '$trajanje',
    'todo'
    )";

    if ($conn->query($sql) === TRUE) {

        echo "<p style='color:green'>
        Obaveza dodata u TODO!
        </p>";

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

    <a href="index.php" style="
        background:#555;
        color:#fff;
        padding:10px 12px;
        border-radius:5px;
        text-decoration:none;
    ">
        ← Nazad
    </a>

</div>


<!-- =========================
     FORM (OSTAJE ISTO)
========================= -->
<form method="POST">

<select name="kategorija">
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
placeholder="Kratak opis"
required
>

<br>

<textarea
name="opis2"
placeholder="Detaljniji opis">
</textarea>

<br>

<input 
type="number"
name="trajanje"
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
</script>

</body>

</html>