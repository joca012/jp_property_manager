<?php
$cols = table_columns($conn, 'stambene_zajednice');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [];
    $map = [
        'naziv' => trim($_POST['naziv'] ?? ''),
        'adresa' => trim($_POST['adresa'] ?? ''),
        'tekuci_racun' => trim($_POST['tekuci_racun'] ?? ''),
        'broj_posebnih_delova' => $_POST['broj_posebnih_delova'] ?? 0,
        'broj_garaznih_mesta' => $_POST['broj_garaznih_mesta'] ?? 0,
        'povrsina_posebnih_delova' => $_POST['povrsina_posebnih_delova'] ?? 0,
        'povrsina_garaznih_mesta' => $_POST['povrsina_garaznih_mesta'] ?? 0,
        'pocetno_stanje_racuna' => $_POST['pocetno_stanje'] ?? 0,
        'pocetno_stanje' => $_POST['pocetno_stanje'] ?? 0,
        'nenaplacena_potrazivanja' => $_POST['nenaplacena_potrazivanja'] ?? 0,
    ];

    if ($map['naziv'] === '') {
        $errors[] = 'Naziv stambene zajednice je obavezan.';
    }

    foreach ($map as $column => $value) {
        if (in_array($column, $cols, true)) {
            $data[$column] = $value;
        }
    }

    if (!$errors && $data) {
        $columns = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO stambene_zajednice (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')';
        $stmt = $conn->prepare($sql);
        $types = str_repeat('s', count($columns));
        $values = array_values($data);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $newId = $conn->insert_id;
        header('Location: index.php?page=zajednica&sz_id=' . $newId);
        exit;
    }
}

$title = 'Dodaj zgradu';
$subtitle = 'Unos osnovnih podataka stambene zajednice.';
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <div class="toolbar"><h2>Nova stambena zajednica</h2><a class="btn btn-light" href="index.php?page=zajednice">← Nazad na zgrade</a></div>
    <?php if ($errors): ?><div class="empty" style="color:#b42318"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post" class="form-grid">
        <div class="field"><label>Naziv</label><input type="text" name="naziv" required></div>
        <div class="field"><label>Adresa</label><input type="text" name="adresa"></div>
        <div class="field"><label>Tekući račun</label><input type="text" name="tekuci_racun"></div>
        <div class="field"><label>Broj posebnih delova</label><input type="number" name="broj_posebnih_delova" value="0"></div>
        <div class="field"><label>Broj garažnih mesta</label><input type="number" name="broj_garaznih_mesta" value="0"></div>
        <div class="field"><label>Površina posebnih delova m²</label><input type="number" step="0.01" name="povrsina_posebnih_delova" value="0"></div>
        <div class="field"><label>Površina garaža m²</label><input type="number" step="0.01" name="povrsina_garaznih_mesta" value="0"></div>
        <div class="field"><label>Početno stanje računa</label><input type="number" step="0.01" name="pocetno_stanje" value="0"></div>
        <div class="field"><label>Nenaplaćena potraživanja</label><input type="number" step="0.01" name="nenaplacena_potrazivanja" value="0"></div>
        <div class="field" style="align-self:end"><button class="btn btn-primary" type="submit">Sačuvaj zgradu</button></div>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
