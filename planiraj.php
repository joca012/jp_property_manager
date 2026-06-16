<?php
include "config.php";

/* =========================
   UZMI ID
========================= */
$id = $_GET['id'] ?? $_POST['id'] ?? null;
$from = $_GET['from'] ?? 'index';

if (!$id) {
    die("Greška: ID nije poslat");
}

$id = (int)$id;

/* =========================
   UCITAJ TASK
========================= */
$result = $conn->query("SELECT * FROM tasks WHERE id=$id");

if (!$result || $result->num_rows == 0) {
    die("Greška: task ne postoji");
}

$task = $result->fetch_assoc();

/* =========================
   PLANIRANJE
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $datum = $_POST['datum'];
    $vreme = $_POST['vreme'];

    /* ---- izračunaj novi interval ---- */
    $start = strtotime("$datum $vreme");
    $end = $start + ($task['trajanje'] * 60);

    /* =========================
       PROVERA PREKLAPANJA
    ========================= */
    $check = $conn->query("
        SELECT * FROM tasks
        WHERE datum = '$datum'
        AND status != 'todo'
        AND status != 'propusteno'
        AND id != $id
    ");

    $ok = true;

    while ($row = $check->fetch_assoc()) {

        if (!$row['vreme']) continue;

        $rowStart = strtotime($row['datum'] . ' ' . $row['vreme']);
        $rowEnd = $rowStart + ($row['trajanje'] * 60);

        if ($start < $rowEnd && $end > $rowStart) {
            $ok = false;
            break;
        }
    }

    if (!$ok) {
        die("❌ Greška: Termin se preklapa sa postojećom obavezom. Izaberi drugi termin.");
    }

    /* =========================
       UPDATE
    ========================= */
    $conn->query("
        UPDATE tasks 
        SET datum='$datum',
            vreme='$vreme',
            status='zakazano'
        WHERE id=$id
    ");

   $redirect = ($from == 'todo') ? 'todo.php' : 'index.php';

echo "<script>
window.parent.location.href = '$redirect';
</script>";
exit;
}
?>

<h2>Planiranje obaveze</h2>

<form method="POST">

    <input type="hidden" name="id" value="<?= $id ?>">

    <p><?= $task['opis1']; ?></p>

    Datum:
    <input type="date" name="datum" required>

    <br><br>

    Vreme:
    <input type="time" name="vreme" required>

    <br><br>

    <button type="submit">Sačuvaj raspored</button>

</form>