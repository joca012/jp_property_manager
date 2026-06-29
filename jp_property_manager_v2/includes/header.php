<?php
$currentPage = $page ?? 'dashboard';
$szId = isset($_GET['sz_id']) ? (int)$_GET['sz_id'] : 0;
$szQuery = $szId > 0 ? '&sz_id=' . $szId : '';
?>
<!doctype html>
<html lang="sr-Latn-RS">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JP Property Manager v2</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="index.php">
            <span class="brand-icon">🏢</span>
            <span><strong>JP</strong><small>Property Manager</small></span>
        </a>
        <nav class="nav">
            <a class="<?= active_page('dashboard', $currentPage) ?>" href="index.php?page=dashboard">📊 Početna</a>
            <a class="<?= active_page('zajednice', $currentPage) ?>" href="index.php?page=zajednice">🏢 Zgrade</a>
            <a class="<?= active_page('izvodjaci', $currentPage) ?>" href="index.php?page=izvodjaci<?= $szQuery ?>">👷 Izvođači</a>
            <a class="<?= active_page('ponude', $currentPage) ?>" href="index.php?page=ponude<?= $szQuery ?>">📑 Ponude</a>
            <?php if ($szId > 0): ?>
                <div class="nav-section">Izabrana zgrada</div>
                <a class="<?= active_page('zajednica', $currentPage) ?>" href="index.php?page=zajednica&sz_id=<?= $szId ?>">🪪 Kartica</a>
                <a class="<?= active_page('finansijski_plan', $currentPage) ?: active_page('budzet', $currentPage) ?>" href="index.php?page=finansijski_plan&sz_id=<?= $szId ?>">💰 Finansijski plan</a>
                <a class="<?= active_page('elementi_zgrade', $currentPage) ?: active_page('elementi_zgrade_pregled', $currentPage) ?>" href="index.php?page=elementi_zgrade_pregled&sz_id=<?= $szId ?>">📋 Popis zgrade</a>
                <a class="<?= active_page('program', $currentPage) ?>" href="index.php?page=program&sz_id=<?= $szId ?>">📅 Program</a>
                <a class="<?= active_page('kvarovi', $currentPage) ?>" href="index.php?page=kvarovi&sz_id=<?= $szId ?>">⚠️ Kvarovi</a>
                <a class="<?= active_page('dokumentacija', $currentPage) ?>" href="index.php?page=dokumentacija&sz_id=<?= $szId ?>">📁 Dokumentacija</a>
                <a class="<?= active_page('izvestaji', $currentPage) ?>" href="index.php?page=izvestaji&sz_id=<?= $szId ?>">📈 Izveštaji</a>
            <?php endif; ?>
        </nav>
    </aside>
    <main class="main">
        <header class="topbar">
            <div>
                <h1><?= e($title ?? 'JP Property Manager') ?></h1>
                <p><?= e($subtitle ?? 'Evidencija, budžet, program održavanja i realizacija.') ?></p>
            </div>
            <div class="topbar-actions">
                <?php if ($szId > 0): ?>
                    <a class="btn btn-light" href="index.php?page=zajednica&sz_id=<?= $szId ?>">← Kartica zgrade</a>
                <?php endif; ?>
            </div>
        </header>
