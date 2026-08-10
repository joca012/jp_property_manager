<?php
$names = [
    'kvarovi' => ['Kvarovi', 'Vanredne intervencije, fotografije, računi i službene beleške.'],
    'dokumentacija' => ['Dokumentacija', 'Dokumenti povezani sa zgradom, aktivnostima, kvarovima i ponudama.'],
    'izvestaji' => ['Izveštaji', 'Realizacija programa, odstupanja i utrošak sredstava.'],
    'ponude' => ['Ponude', 'Evidencija ponuda, stavki i dokumenata uz ponudu.'],
    'izvodjaci' => ['Izvođači', 'Izvođači radova i njihove kategorije delatnosti.'],
];
$key = basename(__FILE__, '.php');
$title = $names[$key][0];
$subtitle = $names[$key][1];
require __DIR__ . '/../includes/header.php';
?>
<section class="card">
    <div class="toolbar"><h2><?= e($title) ?></h2><button class="btn btn-primary">+ Dodaj</button></div>
    <div class="empty">Ovo je v2 osnova modula. Sledeći korak je povezivanje postojeće logike i formi iz v1.</div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
