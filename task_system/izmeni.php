<?php
include "config.php";

/* =========================
   UZMI ID
========================= */
$id = $_GET['id'] ?? $_POST['id'] ?? null;
$return = $_GET['return'] ?? $_POST['return'] ?? 'index.php';

if (!$id) {
    die("Greška: ID nije poslat");
}

$id = (int)$id;

/* =========================
   UČITAJ TASK
========================= */
$result = $conn->query("SELECT * FROM tasks WHERE id = $id");

if (!$result || $result->num_rows == 0) {
    die("Greška: obaveza ne postoji");
}

$task = $result->fetch_assoc();

/* =========================
   SNIMANJE IZMENA
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $kategorija = $conn->real_escape_string($_POST['kategorija'] ?? '');
    $opis1 = $conn->real_escape_string($_POST['opis1'] ?? '');
    $opis2 = $conn->real_escape_string($_POST['opis2'] ?? '');

    $trajanje_dani = (int)($_POST['trajanje_dani'] ?? 0);
    $trajanje_sati = (int)($_POST['trajanje_sati'] ?? 0);
    $trajanje_minuti = (int)($_POST['trajanje_minuti'] ?? 0);

    $trajanje = ($trajanje_dani * 1440) + ($trajanje_sati * 60) + $trajanje_minuti;

    if ($trajanje <= 0) {
        $trajanje = 30;
    }

    $sql = "
        UPDATE tasks
        SET kategorija = '$kategorija',
            opis1 = '$opis1',
            opis2 = '$opis2',
            trajanje = $trajanje
        WHERE id = $id
    ";

    if ($conn->query($sql)) {
        header("Location: $return");
        exit;
    } else {
        echo "Greška: " . $conn->error;
        exit;
    }
}

/* =========================
   PRIPREMA TRAJANJA
========================= */
$trajanje = (int)$task['trajanje'];

if ($trajanje <= 0) {
    $trajanje = 30;
}

$trajanje_dani = intdiv($trajanje, 1440);
$ostatak = $trajanje % 1440;

$trajanje_sati = intdiv($ostatak, 60);
$trajanje_minuti = $ostatak % 60;

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Izmeni obavezu</title>

<style>
body {
    font-family: Arial;
    padding: 20px;
    background: #f2f2f2;
}

.box {
    background: white;
    padding: 20px;
    border-radius: 8px;
    max-width: 520px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}

input, textarea, select {
    width: 100%;
    padding: 8px;
    margin: 5px 0 12px 0;
    box-sizing: border-box;
}

.trajanje-row {
    display: flex;
    gap: 10px;
}

.trajanje-row div {
    flex: 1;
}

button, a.button {
    display: inline-block;
    padding: 10px 15px;
    background: #444;
    color: white;
    border: none;
    border-radius: 5px;
    text-decoration: none;
    cursor: pointer;
}

button {
    background: #198754;
}

.info {
    background: #fff3cd;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
    font-size: 14px;
}
</style>
</head>

<body>

<div class="box">

<h2>✏ Izmeni obavezu</h2>

<div class="info">
    Izmena ne menja datum, vreme ni status obaveze.
</div>

<form method="POST">

    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="return" value="<?= htmlspecialchars($return) ?>">

    <label>Kategorija</label>
    <select name="kategorija" required>
        <?php
        $kategorije = ['JA', 'EPS', 'PIDRA', 'PLAC', 'SAFE_LIFE'];

        foreach ($kategorije as $kat) {
            $selected = ($task['kategorija'] == $kat) ? 'selected' : '';
            echo "<option value='$kat' $selected>$kat</option>";
        }
        ?>
    </select>

    <label>Kratak opis</label>
    <input
        type="text"
        name="opis1"
        value="<?= htmlspecialchars($task['opis1']) ?>"
        required
    >

    <label>Detaljniji opis</label>
    <textarea name="opis2" rows="4"><?= htmlspecialchars($task['opis2']) ?></textarea>

    <label>Trajanje</label>
    <div class="trajanje-row">
        <div>
            <small>Dani</small>
            <input type="number" name="trajanje_dani" min="0" value="<?= $trajanje_dani ?>">
        </div>

        <div>
            <small>Sati</small>
            <input type="number" name="trajanje_sati" min="0" value="<?= $trajanje_sati ?>">
        </div>

        <div>
            <small>Minuta</small>
            <input type="number" name="trajanje_minuti" min="0" value="<?= $trajanje_minuti ?>">
        </div>
    </div>

    <button type="submit">Sačuvaj izmene</button>

    <a class="button" href="<?= htmlspecialchars($return) ?>">Nazad</a>

</form>

</div>

</body>
</html>
