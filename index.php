<?php
include "config.php";

$kategorija = $_GET['kategorija'] ?? 'JA';

$sql = "SELECT * FROM tasks 
        WHERE kategorija='$kategorija'
        ORDER BY datum, vreme";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Task System</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f2f2f2;
        }

        .header {
            background: #222;
            padding: 15px;
            display: flex;
            gap: 10px;
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

        .left {
            width: 50%;
            background: white;
            padding: 20px;
        }

        .right {
            width: 50%;
            background: #ddd;
            padding: 20px;
        }

    </style>

</head>

<body>


<div class="header">

	<a href="?kategorija=JA">JA</a>

	<a href="?kategorija=EPS">EPS</a>

	<a href="?kategorija=PIDRA">PIDRA</a>

	<a href="?kategorija=PLAC">PLAC</a>

	<a href="?kategorija=SAFE_LIFE">SAFE LIFE</a>

    <a class="todo" href="todo.php">
        TODO
    </a>

</div>



<div class="container">

    <div class="left">

        <h2>Dnevni raspored</h2>

        <p>
            <?php

if ($result->num_rows > 0) {

    while($row = $result->fetch_assoc()) {

        echo "
        <div style='
            border:1px solid #ccc;
            padding:10px;
            margin-bottom:10px;
        '>

        <b>{$row['vreme']}</b><br>

        {$row['opis1']}<br>

        {$row['opis2']}<br>

        Status: {$row['status']}

        </div>
        ";

    }

}
else {

    echo "Nema obaveza za ovu kategoriju.";

}

?>
        </p>


    </div>


    <div class="right">

        <h2>Kalendar</h2>

        <p>
            Ovde dolazi mesečni kalendar
        </p>


    </div>


</div>


</body>
</html>