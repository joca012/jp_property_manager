<?php
$nazad = $_SERVER['HTTP_REFERER'] ?? null;
?>

<div class="top-nav">

    <div class="brand">
        <a href="index.php">JP Property Manager</a>
    </div>

    <div class="menu">

        <?php if ($nazad): ?>
            <a href="<?= htmlspecialchars($nazad) ?>">⬅ Nazad</a>
        <?php endif; ?>

        <a href="index.php">🏢 Zajednice</a>
        <a href="izvodjaci.php">🏭 Izvođači</a>
        <a href="ponude.php">📄 Ponude</a>

    </div>

</div>