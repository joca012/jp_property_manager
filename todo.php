<?php

include "config.php";


if ($_SERVER["REQUEST_METHOD"] == "POST") {


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

<a href='planiraj.php?id={$row['id']}'>
Planiraj
</a>


</div>

";


}



?>


</body>

</html>