<?php
include "config.php";

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Nedostaje ID zadatka.");
}

$id = (int)$id;

/* =========================
   UČITAVANJE ZADATKA
========================= */
$result = $conn->query("SELECT * FROM tasks WHERE id=$id");

if (!$result || $result->num_rows == 0) {
    die("Zadatak ne postoji.");
}

$task = $result->fetch_assoc();

/* =========================
   SAVE
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	
	
	$datetime = $_POST['start_datetime'] ?? null;

if (!$datetime) {
    die("Niste uneli datum i vreme.");
}

$start = strtotime($datetime);

$trajanje_sati = (int)($_POST['trajanje_sati'] ?? 0);
$trajanje_minuti = (int)($_POST['trajanje_minuti'] ?? 0);

$trajanje = ($trajanje_sati * 60) + $trajanje_minuti;

$end = $start + ($trajanje * 60);

/* =========================
   PROVERA PREKLAPANJA
========================= */

$check = $conn->query("
    SELECT * FROM tasks 
    WHERE datum = '" . date("Y-m-d", $start) . "'
");

while ($row = $check->fetch_assoc()) {

    $existingStart = strtotime($row['datum'] . " " . $row['vreme']);
    $existingEnd = $existingStart + ($row['trajanje'] * 60);

    if ($start < $existingEnd && $end > $existingStart) {
        echo "❌ Termin se preklapa sa postojećim zadatkom.<br><br>
<a href='javascript:history.back()'>← Izaberi drugi termin</a>";
exit;
    }
}

    $datetime = $_POST['start_datetime'] ?? null;

    $trajanje_sati = (int)($_POST['trajanje_sati'] ?? 0);
    $trajanje_minuti = (int)($_POST['trajanje_minuti'] ?? 0);

    if (!$datetime) {
        die("Niste uneli datum i vreme.");
    }

    $date = date("Y-m-d", strtotime($datetime));
    $time = date("H:i", strtotime($datetime));

    $trajanje = ($trajanje_sati * 60) + $trajanje_minuti;

    $sql = "
        UPDATE tasks SET
            datum = '$date',
            vreme = '$time',
            trajanje = $trajanje,
            status = 'zakazano'
        WHERE id = $id
    ";

    $conn->query($sql);

echo "<script>
window.parent.location.reload();
</script>";
exit;
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Planiranje</title>

<style>
body {
    font-family: Arial;
    padding: 20px;
}

input {
    padding: 6px;
    margin: 5px 0;
}
</style>
</head>

<body>

<h2>Planiranje obaveze</h2>

<p><b><?php echo $task['opis1']; ?></b></p>

<form method="POST">

    <label>Datum i vreme:</label><br>
    <input type="datetime-local" name="start_datetime" required>

    <br><br>

    <label>Trajanje:</label><br>

    <input type="number" name="trajanje_sati" min="0" value="0"> sati
    <input type="number" name="trajanje_minuti" min="0" max="59" value="30"> min

    <br><br>

    <button type="submit">
        Sačuvaj raspored
    </button>

</form>

</body>
</html>