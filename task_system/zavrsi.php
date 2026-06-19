<?php

include "config.php";


$id = $_GET['id'];


$sql = "

UPDATE tasks

SET status='zavrseno'

WHERE id=$id

";


$conn->query($sql);


header("Location: index.php");

?>