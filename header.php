<?php
$nazad = $_SERVER['HTTP_REFERER'] ?? null;
$trenutna_stranica = basename($_SERVER['PHP_SELF']);
$sz_id = isset($sz_id) ? (int)$sz_id : 0;

if ($sz_id <= 0 && isset($_GET['sz_id'])) {
    $sz_id = (int)$_GET['sz_id'];
} elseif ($sz_id <= 0 && isset($_POST['sz_id'])) {
    $sz_id = (int)$_POST['sz_id'];
} elseif ($sz_id <= 0 && $trenutna_stranica === 'zajednica.php' && isset($_GET['id'])) {
    $sz_id = (int)$_GET['id'];
}

function active_link($file) {
    return basename($_SERVER['PHP_SELF']) === $file ? ' class="active"' : '';
}

$ctx = $sz_id > 0 ? '?sz_id=' . $sz_id : '';
?>

<style>
    .top-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        padding: 12px 18px;
        margin-bottom: 18px;
        background: #ffffff;
        border-bottom: 1px solid #ddd;
        box-shadow: 0 1px 4px rgba(0,0,0,.06);
    }
    .top-nav .brand a {
        font-size: 20px;
        font-weight: 700;
        text-decoration: none;
        color: #222;
        white-space: nowrap;
    }
    .top-nav .menu {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }
    .top-nav .menu a {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        min-height: 34px;
        padding: 7px 10px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background: #f8f8f8;
        color: #222;
        text-decoration: none;
        font-size: 14px;
        line-height: 1;
        white-space: nowrap;
    }
    .top-nav .menu a:hover,
    .top-nav .menu a.active {
        background: #ececec;
        border-color: #cfcfcf;
    }
    .page-actions, .table-actions, .action-cell {
        display: flex;
        gap: 6px;
        align-items: center;
        flex-wrap: wrap;
    }
    .action-cell { justify-content: flex-start; }
    .btn, button, .button-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        min-height: 34px;
        padding: 7px 10px;
        border-radius: 8px;
        border: 1px solid #ccc;
        text-decoration: none;
        cursor: pointer;
        line-height: 1.1;
        box-sizing: border-box;
        vertical-align: middle;
    }
    .btn-small { min-height: 30px; padding: 5px 8px; font-size: 13px; }
    .btn-danger { background: #fff4f4; border-color: #e5b8b8; }
    .btn-secondary { background: #f8f8f8; }
    .card {
        background: #fff;
        border: 1px solid #e1e1e1;
        border-radius: 10px;
        padding: 14px;
        margin-bottom: 16px;
    }
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 10px 14px;
        align-items: end;
    }
    .form-grid label { display:block; margin-bottom:4px; font-weight:600; }
    .table-wrap { overflow-x:auto; }
    table td, table th { vertical-align: middle; }
</style>

<div class="top-nav">
    <div class="brand">
        <a href="index.php">🏢 JP Properties</a>
    </div>

    <div class="menu">
        <?php if ($nazad): ?>
            <a href="<?= htmlspecialchars($nazad) ?>">⬅ Nazad</a>
        <?php endif; ?>

        <a href="izvodjaci.php<?= $ctx ?>"<?= active_link('izvodjaci.php') ?>>👷 Izvođači</a>
        <a href="ponude.php<?= $ctx ?>"<?= active_link('ponude.php') ?>>📄 Ponude</a>

        <?php if ($sz_id > 0): ?>
            <a href="budzet.php?sz_id=<?= $sz_id ?>"<?= active_link('budzet.php') ?>>💰 Budžet</a>
            <a href="oprema.php?sz_id=<?= $sz_id ?>"<?= active_link('oprema.php') ?>>🧰 Oprema</a>
            <a href="program_odrzavanja.php?sz_id=<?= $sz_id ?>"<?= active_link('program_odrzavanja.php') ?>>📅 Program</a>
            <a href="kvarovi.php?sz_id=<?= $sz_id ?>"<?= active_link('kvarovi.php') ?>>🛠️ Kvarovi</a>
            <a href="dokumentacija.php?sz_id=<?= $sz_id ?>"<?= active_link('dokumentacija.php') ?>>📁 Dokumentacija</a>
        <?php endif; ?>
    </div>
</div>
