<?php
include "config.php";

/* =========================
   DODAVANJE NOVE SMENE
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['dodaj_smenu'])) {

    $naziv = $_POST['naziv'];
    $kategorija = "EPS";
    $opis1 = $_POST['naziv'];
    $opis2 = $_POST['opis2'];
    $vreme = $_POST['vreme'];
    $trajanje = (int)$_POST['trajanje'];

    $sql = "INSERT INTO sabloni
            (naziv, kategorija, opis1, opis2, vreme, trajanje, tip)
            VALUES
            ('$naziv', '$kategorija', '$opis1', '$opis2', '$vreme', $trajanje, 'smena')";

    $conn->query($sql);

    header("Location: smene.php");
    exit;
}

/* =========================
   DODAVANJE NOVOG CIKLUSA
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['dodaj_ciklus'])) {

    $naziv = $_POST['naziv_ciklusa'];
    $opis = $_POST['opis_ciklusa'];

    $conn->query("
        INSERT INTO ciklusi (naziv, opis)
        VALUES ('$naziv', '$opis')
    ");

    $ciklus_id = $conn->insert_id;

    $stavke = [
        [
            'sablon_id' => (int)$_POST['prva_smena'],
            'broj_dana' => (int)$_POST['prva_dana'],
            'tip' => 'smena'
        ],
        [
            'sablon_id' => (int)$_POST['druga_smena'],
            'broj_dana' => (int)$_POST['druga_dana'],
            'tip' => 'smena'
        ],
        [
            'sablon_id' => (int)$_POST['treca_smena'],
            'broj_dana' => (int)$_POST['treca_dana'],
            'tip' => 'smena'
        ],
        [
            'sablon_id' => "NULL",
            'broj_dana' => (int)$_POST['slobodno_dana'],
            'tip' => 'slobodno'
        ]
    ];

    $redosled = 1;

    foreach ($stavke as $stavka) {

        $sablon_id = $stavka['sablon_id'];
        $broj_dana = $stavka['broj_dana'];
        $tip = $stavka['tip'];

        if ($broj_dana <= 0) {
            continue;
        }

        $conn->query("
            INSERT INTO ciklus_stavke
            (ciklus_id, sablon_id, redosled, broj_dana, tip)
            VALUES
            ($ciklus_id, $sablon_id, $redosled, $broj_dana, '$tip')
        ");

        $redosled++;
    }

    header("Location: smene.php");
    exit;
}

/* =========================
   UČITAJ SMENE I CIKLUSE
========================= */
$smene = $conn->query("
    SELECT * FROM sabloni
    WHERE tip = 'smena'
    ORDER BY vreme
");

$smeneZaFormu = $conn->query("
    SELECT * FROM sabloni
    WHERE tip = 'smena'
    ORDER BY vreme
");

$ciklusi = $conn->query("
    SELECT * FROM ciklusi
    ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Smene</title>

<style>
body {
    font-family: Arial;
    padding: 20px;
    background: #f2f2f2;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header a {
    background: #555;
    color: white;
    padding: 10px 12px;
    border-radius: 5px;
    text-decoration: none;
}

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.box, .card {
    background: white;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

input, textarea, select {
    width: 300px;
    padding: 8px;
    margin: 5px 0 12px 0;
}

button {
    padding: 10px 20px;
    background: #222;
    color: white;
    border: none;
    border-radius: 5px;
}

.stavka {
    background:#eee;
    padding:8px;
    margin-top:6px;
    border-radius:4px;
}
</style>
</head>

<body>

<div class="header">
    <h2>Smene i ciklusi</h2>

    <div>
        <a href="generisi_smene.php">← Nazad</a>
    </div>
</div>

<div class="grid">

<!-- =========================
     LEVA STRANA - SMENE
========================= -->
<div>

<div class="box">
<h3>Nova smena</h3>

<form method="POST">

    <input type="hidden" name="dodaj_smenu" value="1">

    <label>Naziv smene</label><br>
    <input type="text" name="naziv" placeholder="npr. Dnevna 12h" required>

    <br>

    <label>Opis</label><br>
    <textarea name="opis2" placeholder="Napomena"></textarea>

    <br>

    <label>Početno vreme</label><br>
    <input type="time" name="vreme" required>

    <br>

    <label>Trajanje u minutima</label><br>
    <input type="number" name="trajanje" placeholder="480 ili 720" required>

    <br>

    <button type="submit">Sačuvaj smenu</button>

</form>
</div>

<h3>Postojeće smene</h3>

<?php
if ($smene && $smene->num_rows > 0) {

    while ($s = $smene->fetch_assoc()) {

        $vremeFormat = date("H:i", strtotime($s['vreme']));

        echo "
        <div class='card'>
            <b>{$s['naziv']}</b><br><br>
            Početak: $vremeFormat<br>
            Trajanje: {$s['trajanje']} min<br>
            Kategorija: {$s['kategorija']}<br><br>
            {$s['opis2']}
        </div>
        ";
    }

} else {
    echo "Nema kreiranih smena.";
}
?>

</div>

<!-- =========================
     DESNA STRANA - CIKLUSI
========================= -->
<div>

<div class="box">
<h3>Novi ciklus</h3>

<form method="POST">

    <input type="hidden" name="dodaj_ciklus" value="1">

    <label>Naziv ciklusa</label><br>
    <input type="text" name="naziv_ciklusa" value="2-2-2-4" required>

    <br>

    <label>Opis</label><br>
    <textarea name="opis_ciklusa">Dve prve, dve druge, dve treće, četiri dana slobodno</textarea>

    <br>

    <label>Prva smena</label><br>
    <select name="prva_smena" required>
        <?php
        $smeneZaFormu->data_seek(0);
        while ($s = $smeneZaFormu->fetch_assoc()) {
            echo "<option value='{$s['id']}'>{$s['naziv']}</option>";
        }
        ?>
    </select>

    <input type="number" name="prva_dana" value="2" required>

    <br>

    <label>Druga smena</label><br>
    <select name="druga_smena" required>
        <?php
        $smeneZaFormu->data_seek(0);
        while ($s = $smeneZaFormu->fetch_assoc()) {
            echo "<option value='{$s['id']}'>{$s['naziv']}</option>";
        }
        ?>
    </select>

    <input type="number" name="druga_dana" value="2" required>

    <br>

    <label>Treća smena</label><br>
    <select name="treca_smena" required>
        <?php
        $smeneZaFormu->data_seek(0);
        while ($s = $smeneZaFormu->fetch_assoc()) {
            echo "<option value='{$s['id']}'>{$s['naziv']}</option>";
        }
        ?>
    </select>

    <input type="number" name="treca_dana" value="2" required>

    <br>

    <label>Slobodno dana</label><br>
    <input type="number" name="slobodno_dana" value="4" required>

    <br>

    <button type="submit">Sačuvaj ciklus</button>

</form>
</div>

<h3>Postojeći ciklusi</h3>

<?php
if ($ciklusi && $ciklusi->num_rows > 0) {

    while ($c = $ciklusi->fetch_assoc()) {

        echo "
        <div class='card'>
            <b>{$c['naziv']}</b><br><br>
            {$c['opis']}<br><br>
        ";

        $stavke = $conn->query("
            SELECT 
                ciklus_stavke.*,
                sabloni.naziv AS naziv_smene
            FROM ciklus_stavke
            LEFT JOIN sabloni ON ciklus_stavke.sablon_id = sabloni.id
            WHERE ciklus_stavke.ciklus_id = {$c['id']}
            ORDER BY ciklus_stavke.redosled
        ");

        while ($st = $stavke->fetch_assoc()) {

            if ($st['tip'] == 'slobodno') {
                echo "<div class='stavka'>Slobodno — {$st['broj_dana']} dana</div>";
            } else {
                echo "<div class='stavka'>{$st['naziv_smene']} — {$st['broj_dana']} dana</div>";
            }
        }

        echo "
        </div>
        ";
    }

} else {
    echo "Nema kreiranih ciklusa.";
}
?>

</div>

</div>

</body>
</html>