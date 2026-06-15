<?php

include "config.php";


$id = $_GET['id'];


$result = $conn->query(
"SELECT * FROM tasks WHERE id=$id"
);


$task = $result->fetch_assoc();


if ($_SERVER["REQUEST_METHOD"]=="POST"){


    $datum = $_POST['datum'];
    $vreme = $_POST['vreme'];


    $sql = "

    UPDATE tasks SET

    datum='$datum',
    vreme='$vreme',
    status='zakazano'

    WHERE id=$id

    ";


    $conn->query($sql);


    header("Location: index.php");

}


?>


<h2>Planiranje obaveze</h2>


<form method="POST">


<p>
<?php echo $task['opis1']; ?>
</p>


Datum:

<input type="date" name="datum" required>


<br><br>


Vreme:

<input type="time" name="vreme" required>


<br><br>


<button>
Sačuvaj raspored
</button>


</form>