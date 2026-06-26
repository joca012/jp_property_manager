<?php
include "config.php";
include "functions.php";

$sql = "SELECT * FROM tasks
        WHERE status = 'obrisano'
        ORDER BY id DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Obrisano</title>

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

.card {
    background: white;
    padding: 12px;
    margin-bottom: 10px;
    border-left: 6px solid #6c757d;
    border-radius: 6px;
}

.badge {
    padding: 3px 8px;
    color: #fff;
    background: #6c757d;
    border-radius: 4px;
    font-size: 12px;
}
</style>
</head>

<body>

<div class="header">
    <a href="index.php">← Nazad</a>
    <a href="logout.php" title="Odjava">Odjava</a>
</div>

<div class="container">

<h2>🗑 Obrisane obaveze</h2>

<?php
if ($result && $result->num_rows > 0) {

    while ($row = $result->fetch_assoc()) {

        $datumFormat = $row['datum'] ? date("d.m.Y.", strtotime($row['datum'])) : "Bez datuma";
        $vremeFormat = $row['vreme'] ? date("H:i", strtotime($row['vreme'])) : "Bez vremena";

        echo "
        <div class='card'>
            <b>$datumFormat $vremeFormat</b> - {$row['kategorija']}<br><br>

            {$row['opis1']}<br>
            {$row['opis2']}<br><br>

            <span class='badge'>obrisano</span>


<br><br>

<a href='restore.php?id={$row['id']}'
   onclick=\"return confirm('Vratiti obavezu u TODO?')\">
   ♻ Vrati
</a>
 | <a href='spali.php?id={$row['id']}'
      onclick=\"return confirm('Trajno uništiti obavezu? Ova radnja se ne može opozvati!')\">
      💥 Spali
   </a>
			
        </div>
        ";
    }

} else {
    echo "Korpa je prazna.";
}
?>

</div>

</body>
</html>