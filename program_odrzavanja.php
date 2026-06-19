<?php
include "config.php";

$sz_id = (int)($_GET['sz_id'] ?? 0);

$result = $conn->query("SELECT * FROM stambene_zajednice WHERE id = $sz_id");

if (!$result || $result->num_rows == 0) {
    die("Stambena zajednica nije pronađena.");
}

$sz = $result->fetch_assoc();

$meseci = [
    1 => 'Januar',
    2 => 'Februar',
    3 => 'Mart',
    4 => 'April',
    5 => 'Maj',
    6 => 'Jun',
    7 => 'Jul',
    8 => 'Avgust',
    9 => 'Septembar',
    10 => 'Oktobar',
    11 => 'Novembar',
    12 => 'Decembar'
];

$program = $conn->query("
    SELECT *
    FROM program_odrzavanja
    WHERE sz_id = $sz_id
    ORDER BY mesec, obavezna DESC, aktivnost
");
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <title>Program održavanja - <?= htmlspecialchars($sz['naziv']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h1>Program održavanja</h1>
            <p>
                <strong><?= htmlspecialchars($sz['naziv']) ?></strong><br>
                <?= htmlspecialchars($sz['adresa']) ?>
            </p>
        </div>

        <a class="btn" href="generisi_program.php?sz_id=<?= $sz_id ?>"
           onclick="return confirm('Generisati novi program? Postojeći program za ovu zgradu biće obrisan.')">
           Generiši program
        </a>
    </div>

    <?php foreach ($meseci as $broj => $naziv): ?>

        <h2><?= $naziv ?></h2>

        <table>
            <tr>
                <th>Aktivnost</th>
                <th>Trošak</th>
                <th>Obavezno</th>
                <th>Status</th>
                <th>Napomena</th>
            </tr>

            <?php
            $ima = false;

            if ($program) {
                $program->data_seek(0);
                while ($row = $program->fetch_assoc()):
                    if ((int)$row['mesec'] !== $broj) {
                        continue;
                    }

                    $ima = true;
            ?>

                <tr>
                    <td><?= htmlspecialchars($row['aktivnost']) ?></td>
                    <td><?= number_format($row['procenjeni_trosak'], 2, ',', '.') ?> RSD</td>
                    <td><?= $row['obavezna'] ? 'Da' : 'Ne' ?></td>
                    <td><?= $row['zavrsena'] ? 'Završeno' : 'Planirano' ?></td>
                    <td><?= nl2br(htmlspecialchars($row['napomena'])) ?></td>
                </tr>

            <?php
                endwhile;
            }

            if (!$ima):
            ?>

                <tr>
                    <td colspan="5">Nema planiranih aktivnosti.</td>
                </tr>

            <?php endif; ?>

        </table>

    <?php endforeach; ?>

    <br>
    <a href="zajednica.php?id=<?= $sz_id ?>">← Nazad na zajednicu</a>

</div>

</body>
</html>