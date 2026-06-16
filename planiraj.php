<?php
include "config.php";

/* =========================
   UZMI ID
========================= */
$id = $_GET['id'] ?? $_POST['id'] ?? null;
$from = $_GET['from'] ?? $_POST['from'] ?? 'index';

if (!$id) {
    die("Greška: ID nije poslat");
}

$id = (int)$id;

/* =========================
   UČITAJ TASK I ŠABLON
========================= */
$result = $conn->query("
    SELECT 
        tasks.*,
        sabloni.tip AS sablon_tip,
        sabloni.vreme AS sablon_vreme
    FROM tasks
    LEFT JOIN sabloni ON tasks.sablon_id = sabloni.id
    WHERE tasks.id = $id
");

if (!$result || $result->num_rows == 0) {
    die("Greška: task ne postoji");
}

$task = $result->fetch_assoc();

$isSmena = ($task['sablon_tip'] == 'smena');

/* =========================
   PLANIRANJE
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $datum = $_POST['datum'];

    if ($isSmena && !empty($task['sablon_vreme'])) {
        $vreme = $task['sablon_vreme'];
    } else {
        $vreme = $_POST['vreme'];
    }

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
        AND status != 'obrisano'
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
    echo "
    <div style='font-family:Arial;padding:20px;'>

        <h3 style='color:#dc3545;'>❌ Termin se preklapa</h3>

        <p>Izabrani termin se preklapa sa postojećom obavezom.</p>

        <a href='planiraj.php?id=$id&from=$from'
           style='
            display:inline-block;
            background:#555;
            color:white;
            padding:10px 15px;
            border-radius:5px;
            text-decoration:none;
           '>
            ← Nazad na planiranje
        </a>

    </div>
    ";
    exit;
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
    <input type="hidden" name="from" value="<?= $from ?>">

    <p>
        <b><?= $task['opis1']; ?></b><br>
        Trajanje: <?= $task['trajanje']; ?> min
    </p>

    Datum:
    <input type="date" name="datum" required>

    <br><br>

    Vreme:
    <?php if ($isSmena && !empty($task['sablon_vreme'])) { ?>

        <input 
            type="time" 
            name="vreme" 
            value="<?= substr($task['sablon_vreme'], 0, 5) ?>" 
            readonly
        >

        <br>
        <small>Vreme je zaključano jer je obaveza kreirana iz šablona smene.</small>

    <?php } else { ?>

        <input type="time" name="vreme" required>

    <?php } ?>

    <br><br>

    <button type="submit">Sačuvaj raspored</button>

</form>