<?php
$title = 'Izvođači';
$subtitle = 'Centralni registar izvođača, dobavljača i poslovnih subjekata.';

$szId = get_int('sz_id');
$szQuery = $szId > 0 ? '&sz_id=' . $szId : '';

function soft_delete_izvodjac_with_related($conn, $id) {
    $stmt = $conn->prepare("UPDATE izvodjaci SET aktivan=0 WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if (function_exists('table_exists') && table_exists($conn, 'racuni_izvodjaca')) {
        $stmt = $conn->prepare("UPDATE racuni_izvodjaca SET aktivan=0 WHERE izvodjac_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }

    if (function_exists('table_exists') && table_exists($conn, 'cenovnici')) {
        if (function_exists('table_exists') && table_exists($conn, 'cenovnik_stavke')) {
            $stmt = $conn->prepare("
                UPDATE cenovnik_stavke cs
                JOIN cenovnici c ON c.id = cs.cenovnik_id
                SET cs.aktivna = 0
                WHERE c.izvodjac_id = ?
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
        }

        $stmt = $conn->prepare("UPDATE cenovnici SET aktivan=0 WHERE izvodjac_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }

    if (function_exists('table_exists') && table_exists($conn, 'ponude')) {
        if (function_exists('table_exists') && table_exists($conn, 'ponuda_stavke')) {
            $stmt = $conn->prepare("
                UPDATE ponuda_stavke ps
                JOIN ponude p ON p.id = ps.ponuda_id
                SET ps.aktivna = 0
                WHERE p.izvodjac_id = ?
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
        }

        $stmt = $conn->prepare("UPDATE ponude SET aktivna=0 WHERE izvodjac_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
}

if (isset($_GET['obrisi'])) {
    $id = (int)$_GET['obrisi'];
    if ($id > 0) {
        soft_delete_izvodjac_with_related($conn, $id);
    }
    redirect_to('index.php?page=izvodjaci' . $szQuery . '&obrisano=1');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_izvodjaca'])) {
    $naziv = trim($_POST['naziv'] ?? '');
    $vrstaSubjektaId = (int)($_POST['vrsta_subjekta_id'] ?? 0);
    $pib = trim($_POST['pib'] ?? '');
    $maticniBroj = trim($_POST['maticni_broj'] ?? '');
    $adresa = trim($_POST['adresa'] ?? '');
    $kontaktOsoba = trim($_POST['kontakt_osoba'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $mobilni = trim($_POST['mobilni'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $web = trim($_POST['web'] ?? '');
    $napomena = trim($_POST['napomena'] ?? '');
    $ocena = (int)($_POST['ocena'] ?? 0);
    $delatnosti = $_POST['delatnosti'] ?? [];

    if ($ocena < 0 || $ocena > 5) { $ocena = 0; }

    if ($naziv !== '') {
        $kategorijaLegacy = '';
        if ($delatnosti) {
            $ids = array_map('intval', $delatnosti);
            $ids = array_filter($ids, fn($v) => $v > 0);
            if ($ids) {
                $in = implode(',', $ids);
                $rows = db_all($conn, "SELECT naziv FROM delatnosti WHERE id IN ($in) ORDER BY naziv");
                $names = array_map(fn($r) => $r['naziv'], $rows);
                $kategorijaLegacy = implode(', ', $names);
            }
        }

        $stmt = $conn->prepare("INSERT INTO izvodjaci
            (naziv, vrsta_subjekta_id, kategorija, pib, maticni_broj, adresa, kontakt_osoba, telefon, mobilni, email, web, napomena, ocena, aktivan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param(
            'sissssssssssi',
            $naziv,
            $vrstaSubjektaId,
            $kategorijaLegacy,
            $pib,
            $maticniBroj,
            $adresa,
            $kontaktOsoba,
            $telefon,
            $mobilni,
            $email,
            $web,
            $napomena,
            $ocena
        );
        $stmt->execute();
        $izvodjacId = $conn->insert_id;

        foreach ($delatnosti as $delatnostId) {
            $delatnostId = (int)$delatnostId;
            if ($delatnostId > 0) {
                $stmtD = $conn->prepare("INSERT INTO izvodjac_delatnosti (izvodjac_id, delatnost_id) VALUES (?, ?)");
                $stmtD->bind_param('ii', $izvodjacId, $delatnostId);
                $stmtD->execute();
            }
        }

        redirect_to('index.php?page=izvodjac_uredi&id=' . $izvodjacId . $szQuery . '&dodato=1');
    }
}

$q = trim($_GET['q'] ?? '');
$where = "i.aktivan=1";
$params = [];
$types = '';
if ($q !== '') {
    $where .= " AND (i.naziv LIKE ? OR i.pib LIKE ? OR i.email LIKE ? OR i.telefon LIKE ? OR i.kategorija LIKE ?)";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like, $like];
    $types = 'sssss';
}

$izvodjaci = db_all(
    $conn,
    "SELECT i.*, vs.naziv AS vrsta_subjekta,
            GROUP_CONCAT(CONCAT(COALESCE(d.ikonica,''), ' ', d.naziv) ORDER BY d.naziv SEPARATOR ', ') AS delatnosti,
            (
                SELECT CONCAT(
                    COALESCE(NULLIF(ri.banka, ''), 'Račun'),
                    CASE WHEN ri.broj_racuna IS NULL OR ri.broj_racuna = '' THEN '' ELSE CONCAT(': ', ri.broj_racuna) END
                )
                FROM racuni_izvodjaca ri
                WHERE ri.izvodjac_id = i.id AND ri.aktivan = 1
                ORDER BY ri.primarni DESC, ri.id ASC
                LIMIT 1
            ) AS primarni_racun
     FROM izvodjaci i
     LEFT JOIN vrste_subjekata vs ON vs.id = i.vrsta_subjekta_id
     LEFT JOIN izvodjac_delatnosti idel ON idel.izvodjac_id = i.id
     LEFT JOIN delatnosti d ON d.id = idel.delatnost_id
     WHERE $where
     GROUP BY i.id
     ORDER BY i.naziv",
    $types,
    $params
);

$vrste = db_all($conn, "SELECT * FROM vrste_subjekata WHERE aktivna=1 ORDER BY naziv");
$delatnosti = db_all($conn, "SELECT * FROM delatnosti WHERE aktivna=1 ORDER BY naziv");

require __DIR__ . '/../includes/header.php';
?>

<style>
.jp-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px 28px; align-items:start; }
.jp-field label { display:block; margin-bottom:6px; font-weight:600; }
.jp-field input, .jp-field select, .jp-field textarea { width:100%; box-sizing:border-box; }
.jp-field-full { grid-column:1 / -1; }
.jp-check-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; }
.jp-check-card { display:flex; gap:8px; align-items:center; padding:10px; min-height:44px; }
@media (max-width: 900px) { .jp-form-grid, .jp-check-grid { grid-template-columns:1fr; } }
</style>


<?php if (isset($_GET['obrisano'])): ?>
    <section class="card" style="border-left:4px solid green;">Izvođač, njegovi cenovnici, ponude i računi su sklonjeni iz aktivne evidencije.</section>
<?php endif; ?>

<section class="card">
    <div class="toolbar">
        <h2>Dodaj izvođača</h2>
    </div>

    <form method="post" class="jp-form-grid">
        <input type="hidden" name="dodaj_izvodjaca" value="1">

        <div class="jp-field jp-field-full">
            <label>Naziv *</label>
            <input type="text" name="naziv" required>
        </div>

        <div class="jp-field">
            <label>Vrsta subjekta</label>
            <select name="vrsta_subjekta_id">
                <option value="0">-- izaberi --</option>
                <?php foreach ($vrste as $v): ?>
                    <option value="<?= (int)$v['id'] ?>"><?= e($v['naziv']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="jp-field"><label>PIB</label><input type="text" name="pib"></div>
        <div class="jp-field"><label>Matični broj</label><input type="text" name="maticni_broj"></div>
        <div class="jp-field"><label>Adresa</label><input type="text" name="adresa"></div>
        <div class="jp-field"><label>Kontakt osoba</label><input type="text" name="kontakt_osoba"></div>
        <div class="jp-field"><label>Telefon</label><input type="text" name="telefon"></div>
        <div class="jp-field"><label>Mobilni</label><input type="text" name="mobilni"></div>
        <div class="jp-field"><label>E-mail</label><input type="email" name="email"></div>
        <div class="jp-field"><label>Web</label><input type="text" name="web"></div>

        <div class="jp-field">
            <label>Ocena</label>
            <select name="ocena">
                <option value="0">Bez ocene</option>
                <?php for ($o=5; $o>=1; $o--): ?>
                    <option value="<?= $o ?>"><?= str_repeat('★', $o) ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="jp-field jp-field-full">
            <label>Delatnosti</label>
            <div class="jp-check-grid">
                <?php foreach ($delatnosti as $d): ?>
                    <label class="card card-muted jp-check-card">
                        <input type="checkbox" name="delatnosti[]" value="<?= (int)$d['id'] ?>">
                        <span><?= e(($d['ikonica'] ? $d['ikonica'] . ' ' : '') . $d['naziv']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="jp-field jp-field-full">
            <label>Napomena</label>
            <textarea name="napomena" rows="3"></textarea>
        </div>

        <div class="jp-field jp-field-full" style="display:flex; justify-content:flex-end;">
            <button class="btn btn-primary" type="submit">Sačuvaj izvođača</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="toolbar">
        <h2>Aktivni izvođači</h2>
        <form method="get" style="display:flex; gap:8px; align-items:center;">
            <input type="hidden" name="page" value="izvodjaci">
            <?php if ($szId > 0): ?><input type="hidden" name="sz_id" value="<?= $szId ?>"><?php endif; ?>
            <input type="text" name="q" value="<?= e($q) ?>" placeholder="Pretraga...">
            <button class="btn btn-light" type="submit">Traži</button>
        </form>
    </div>

    <?php if (!$izvodjaci): ?>
        <div class="empty">Nema evidentiranih izvođača.</div>
    <?php else: ?>
        <table class="table" style="width:100%; border-collapse:collapse; table-layout:fixed;">
            <thead>
                <tr>
                    <th style="text-align:left; width:38%;">Naziv</th>
                    <th style="text-align:left; width:11%;">Vrsta</th>
                    <th style="text-align:left; width:21%;">Delatnosti</th>
                    <th style="text-align:left; width:13%;">Kontakt</th>
                    <th style="text-align:left; width:17%;">Primarni račun</th>
                    <th style="text-align:right; width:240px;">Akcije</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($izvodjaci as $i): ?>
                    <tr>
                        <td style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= e($i['naziv']) ?>">
                            <strong><?= e($i['naziv']) ?></strong>
                            <?php if (!empty($i['pib'])): ?><br><span class="muted">PIB: <?= e($i['pib']) ?></span><?php endif; ?>
                        </td>
                        <td><?= e($i['vrsta_subjekta'] ?? '') ?></td>
                        <td><?= e($i['delatnosti'] ?: ($i['kategorija'] ?? '')) ?></td>
                        <td>
                            <?= e($i['telefon'] ?? '') ?>
                            <?php if (!empty($i['email'])): ?><br><a href="mailto:<?= e($i['email']) ?>"><?= e($i['email']) ?></a><?php endif; ?>
                        </td>
                        <td><?= e($i['primarni_racun'] ?? '') ?></td>
                        <td style="text-align:right; white-space:nowrap;">
                            <a class="btn btn-light btn-sm" href="index.php?page=izvodjac&id=<?= (int)$i['id'] ?><?= $szQuery ?>">Otvori</a>
                            <a class="btn btn-light btn-sm" href="index.php?page=izvodjac_uredi&id=<?= (int)$i['id'] ?><?= $szQuery ?>">Uredi</a>
                            <a class="btn btn-danger btn-sm"
                               href="index.php?page=izvodjaci<?= $szQuery ?>&obrisi=<?= (int)$i['id'] ?>"
                               data-confirm="Obrisati izvođača iz aktivne evidencije? Biće sklonjeni i njegovi cenovnici, ponude i računi. Ništa se ne briše trajno.">
                                Obriši
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
