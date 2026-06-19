<?php
include "config.php";

/* =========================
   DODAVANJE NOVOG ŠABLONA
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $naziv = $_POST['naziv'] ?? '';
    $kategorija = $_POST['kategorija'] ?? '';
    $opis1 = $_POST['opis1'] ?? '';
    $opis2 = $_POST['opis2'] ?? '';
    $vreme = $_POST['vreme'] ?: null;
    $trajanje = (int)($_POST['trajanje'] ?? 0);
    $tip = $_POST['tip'] ?? 'obaveza';

    if ($naziv != '' && $kategorija != '' && $trajanje > 0) {

        $vremeSql = $vreme ? "'$vreme'" : "NULL";

        $sql = "INSERT INTO sabloni
                (naziv, kategorija, opis1, opis2, vreme, trajanje, tip)
                VALUES
                ('$naziv', '$kategorija', '$opis1', '$opis2', $vremeSql, $trajanje, '$tip')";

        $conn->query($sql);
    }

    header("Location: sabloni.php");
    exit;
}

/* =========================
   UČITAVANJE ŠABLONA
========================= */
$sql = "SELECT * FROM sabloni ORDER BY tip, naziv";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Šabloni</title>

<style>
body {
    font-family: Arial;
    margin: 0;
    background: #f2f2f2;
}

.header {
    background: #222;
    padding: 15px;
}

.header a {
    color: white;
    text-decoration: none;
    padding: 10px 20px;
    background: #555;
    border-radius: 5px;
}

.container {
    padding: 20px;
}

.form-box, .card {
    background: white;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 6px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

input, select, textarea {
    width: 100%;
    padding: 8px;
    margin: 6px 0 12px 0;
    box-sizing: border-box;
}

button {
    padding: 10px 20px;
    background: #222;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.badge {
    padding: 3px 8px;
    color: white;
    background: #444;
    border-radius: 4px;
    font-size: 12px;
}

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
</style>
</head>

<body>

<div class="header">
    <a href="index.php">← Nazad</a>
</div>

<div class="container">

<h2>Šabloni i smene</h2>

<div class="grid">

<!-- =========================
     FORMA ZA NOVI ŠABLON
========================= -->
<div class="form-box">

<h3>Novi šablon</h3>

<form method="POST">

    <label>Naziv šablona</label>
    <input type="text" name="naziv" required>

    <label>Kategorija</label>
    <select name="kategorija" required>
        <option value="">-- Izaberi --</option>
        <option value="JA">JA</option>
        <option value="EPS">EPS</option>
        <option value="PIDRA">PIDRA</option>
        <option value="PLAC">PLAC</option>
        <option value="SAFE_LIFE">SAFE LIFE</option>
    </select>

    <label>Opis 1</label>
    <input type="text" name="opis1">

    <label>Opis 2</label>
    <textarea name="opis2"></textarea>

    <label>Vreme početka</label>
    <input type="time" name="vreme">

    <label>Trajanje u minutima</label>
    <input type="number" name="trajanje" required>

    <label>Tip</label>
    <select name="tip">
        <option value="obaveza">Obaveza</option>
        <option value="smena">Smena</option>
    </select>

    <button type="submit">Sačuvaj šablon</button>

</form>

</div>

<!-- =========================
     LISTA ŠABLONA
========================= -->
<div>

<h3>Postojeći šabloni</h3>

<?php
if ($result && $result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {

        $vremeFormat = $row['vreme'] ? date("H:i", strtotime($row['vreme'])) : "Bez vremena";

        echo "
        <div class='card'>
            <b>{$row['naziv']}</b>
            <span class='badge'>{$row['tip']}</span>
            <br><br>

            Kategorija: {$row['kategorija']}<br>
            Vreme: $vremeFormat<br>
            Trajanje: {$row['trajanje']} min<br><br>

            {$row['opis1']}<br>
            {$row['opis2']}
        </div>
        ";
    }

} else {
    echo "Još nema šablona.";
}
?>

</div>

</div>

</div>

</body>
</html>