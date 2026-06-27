<?php
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money_rs($value) {
    return number_format((float)$value, 2, ',', '.') . ' RSD';
}

function get_int($key, $default = 0) {
    return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
}

function active_page($page, $current) {
    return $page === $current ? 'active' : '';
}

function db_one($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return null; }
    if ($types && $params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function db_all($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return []; }
    if ($types && $params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function table_columns($conn, $table) {
    static $cache = [];
    if (isset($cache[$table])) { return $cache[$table]; }
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM `$safe`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
    }
    $cache[$table] = $cols;
    return $cols;
}

function has_column($conn, $table, $column) {
    return in_array($column, table_columns($conn, $table), true);
}

function first_existing_column($conn, $table, $candidates, $fallback = null) {
    foreach ($candidates as $candidate) {
        if (has_column($conn, $table, $candidate)) { return $candidate; }
    }
    return $fallback;
}


function post_value($key, $default = '') {
    return $_POST[$key] ?? $default;
}

function redirect_to($url) {
    header('Location: ' . $url);
    exit;
}

function current_year() {
    return (int)date('Y');
}

function ensure_finansijski_plan_schema($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS finansijski_planovi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sz_id INT NOT NULL,
        godina INT NOT NULL,
        tekuce_po_delu DECIMAL(12,2) NOT NULL DEFAULT 0,
        upravljanje_po_delu DECIMAL(12,2) NOT NULL DEFAULT 0,
        garaza_po_mestu DECIMAL(12,2) NOT NULL DEFAULT 0,
        investiciono_po_m2 DECIMAL(12,2) NOT NULL DEFAULT 0,
        stepen_naplate DECIMAL(5,2) NOT NULL DEFAULT 100,
        nepredvidjeni_proc DECIMAL(5,2) NOT NULL DEFAULT 0,
        napomena TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sz_godina (sz_id, godina)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS finansijski_plan_stavke (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plan_id INT NOT NULL,
        tip ENUM('priliv','odliv') NOT NULL,
        naziv VARCHAR(190) NOT NULL,
        grupa VARCHAR(120) NULL,
        period ENUM('mesecno','godisnje','jednokratno') NOT NULL DEFAULT 'godisnje',
        iznos DECIMAL(12,2) NOT NULL DEFAULT 0,
        napomena TEXT NULL,
        predefinisana TINYINT(1) NOT NULL DEFAULT 0,
        aktivna TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_plan_tip (plan_id, tip),
        CONSTRAINT fk_fin_plan_stavke_plan FOREIGN KEY (plan_id) REFERENCES finansijski_planovi(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS finansijski_plan_rebalansi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plan_id INT NOT NULL,
        datum DATE NOT NULL,
        razlog TEXT NULL,
        snapshot_json LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_plan_rebalans (plan_id, datum),
        CONSTRAINT fk_fin_plan_rebalansi_plan FOREIGN KEY (plan_id) REFERENCES finansijski_planovi(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // v2.1: stavke finansijskog plana dobijaju mesečno važenje i osnov obračuna.
    // Ovo je kompatibilno sa starim stavkama: stare se tretiraju kao fiksne godišnje/mesečne stavke.
    $col = fn($name) => $conn->query("SHOW COLUMNS FROM finansijski_plan_stavke LIKE '" . $conn->real_escape_string($name) . "'");
    $res = $col('osnov');
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE finansijski_plan_stavke ADD COLUMN osnov VARCHAR(40) NOT NULL DEFAULT 'fiksno' AFTER period");
    }
    $res = $col('mesec_od');
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE finansijski_plan_stavke ADD COLUMN mesec_od TINYINT NOT NULL DEFAULT 1 AFTER iznos");
    }
    $res = $col('mesec_do');
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE finansijski_plan_stavke ADD COLUMN mesec_do TINYINT NOT NULL DEFAULT 12 AFTER mesec_od");
    }
    $res = $col('izvor');
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE finansijski_plan_stavke ADD COLUMN izvor VARCHAR(60) NULL AFTER predefinisana");
    }
}

function get_or_create_finansijski_plan($conn, $szId, $godina) {
    $plan = db_one($conn, "SELECT * FROM finansijski_planovi WHERE sz_id=? AND godina=?", 'ii', [$szId, $godina]);
    if ($plan) { return $plan; }

    $stmt = $conn->prepare("INSERT INTO finansijski_planovi (sz_id, godina, stepen_naplate) VALUES (?, ?, 90)");
    $stmt->bind_param('ii', $szId, $godina);
    $stmt->execute();
    $planId = $conn->insert_id;

    seed_finansijski_plan_stavke($conn, $planId);
    return db_one($conn, "SELECT * FROM finansijski_planovi WHERE id=?", 'i', [$planId]);
}

function seed_finansijski_plan_stavke($conn, $planId) {
    $existing = db_one($conn, "SELECT COUNT(*) AS c FROM finansijski_plan_stavke WHERE plan_id=?", 'i', [$planId]);
    if ($existing && (int)$existing['c'] > 0) { return; }
    $stavke = [
        ['odliv','Profesionalni upravnik','Upravljanje','mesecno',0,1],
        ['odliv','Bankarski troškovi','Opšti troškovi','godisnje',0,1],
        ['odliv','Čišćenje','Tekuće održavanje','mesecno',0,1],
        ['odliv','Lift','Oprema','mesecno',0,1],
        ['odliv','Osiguranje','Opšti troškovi','godisnje',0,1],
        ['odliv','Električna energija','Tekuće održavanje','mesecno',0,1],
        ['odliv','PP aparati','Oprema','godisnje',0,1],
        ['odliv','Hidranti','Oprema','godisnje',0,1],
        ['odliv','Dimničar','Tekuće održavanje','godisnje',0,1],
        ['odliv','Ostali planirani odliv','Ostalo','godisnje',0,1],
        ['priliv','Ostali planirani priliv','Ostalo','godisnje',0,0],
    ];
    $stmt = $conn->prepare("INSERT INTO finansijski_plan_stavke (tip, naziv, grupa, period, iznos, predefinisana, plan_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($stavke as $row) {
        [$tip,$naziv,$grupa,$period,$iznos,$predef] = $row;
        $stmt->bind_param('ssssdii', $tip, $naziv, $grupa, $period, $iznos, $predef, $planId);
        $stmt->execute();
    }
}

function period_to_yearly($period, $iznos) {
    $iznos = (float)$iznos;
    if ($period === 'mesecno') { return $iznos * 12; }
    return $iznos;
}

function get_building_metric($z, $conn, $candidates, $default = 0) {
    foreach ($candidates as $candidate) {
        if (array_key_exists($candidate, $z)) {
            return (float)$z[$candidate];
        }
    }
    return $default;
}


function table_exists($conn, $table) {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$safe'");
    return $res && $res->num_rows > 0;
}

function sync_finansijski_plan_from_v1_budzet($conn, $plan, $z, $godina) {
    if (!$plan || !$z || !table_exists($conn, 'budzeti')) { return $plan; }
    $szId = (int)$z['id'];
    $legacy = db_one($conn, "SELECT * FROM budzeti WHERE sz_id=? AND godina=? ORDER BY (status='aktivan') DESC, id DESC LIMIT 1", 'ii', [$szId, $godina]);
    if (!$legacy) { return $plan; }

    $isEmptyPlan =
        (float)($plan['tekuce_po_delu'] ?? 0) == 0 &&
        (float)($plan['upravljanje_po_delu'] ?? 0) == 0 &&
        (float)($plan['garaza_po_mestu'] ?? 0) == 0 &&
        (float)($plan['investiciono_po_m2'] ?? 0) == 0;

    if ($isEmptyPlan) {
        $tekuce = (float)($legacy['tekuce_po_posebnom_delu'] ?? 0);
        $upravljanje = (float)($legacy['profesionalni_upravnik_po_posebnom_delu'] ?? 0);
        $garaza = (float)($legacy['garazno_mesto_mesecno'] ?? 0);
        $invest = (float)($legacy['investiciono_po_m2'] ?? 0);
        $naplata = (float)($legacy['procenat_naplate'] ?? 100);
        $stmt = $conn->prepare("UPDATE finansijski_planovi SET tekuce_po_delu=?, upravljanje_po_delu=?, garaza_po_mestu=?, investiciono_po_m2=?, stepen_naplate=? WHERE id=?");
        $stmt->bind_param('dddddi', $tekuce, $upravljanje, $garaza, $invest, $naplata, $plan['id']);
        $stmt->execute();
    }

    $planId = (int)$plan['id'];

    // Bankarski troškovi iz v1: tamo su bili mesečni, u v2 ih prikazujemo kao godišnji planirani odliv.
    $bankarskiGod = ((float)($legacy['bankarski_troskovi_mesecno'] ?? 0)) * 12;
    if ($bankarskiGod > 0) {
        upsert_plan_stavka_by_name($conn, $planId, 'odliv', 'Bankarski troškovi', 'Opšti troškovi', 'godisnje', $bankarskiGod, 1);
    }

    // Nenaplaćena potraživanja iz ranijih godina ulaze kao očekivani priliv.
    $potrazivanja = (float)($legacy['nenaplacena_potrazivanja'] ?? 0);
    $procPotrazivanja = (float)($legacy['procenat_naplate_potrazivanja'] ?? 0);
    $ocekivanaPotrazivanja = $potrazivanja * ($procPotrazivanja / 100);
    if ($ocekivanaPotrazivanja > 0) {
        upsert_plan_stavka_by_name($conn, $planId, 'priliv', 'Očekivana naplata potraživanja iz ranijih godina', 'Potraživanja', 'godisnje', $ocekivanaPotrazivanja, 1);
    }

    // Nepredviđeni troškovi iz v1 su bili fiksni godišnji iznos, zato ih prenosimo kao posebnu stavku.
    $nepredGod = (float)($legacy['nepredvidjeni_troskovi_godisnje'] ?? 0);
    if ($nepredGod > 0) {
        upsert_plan_stavka_by_name($conn, $planId, 'odliv', 'Nepredviđeni troškovi', 'Rezerva', 'godisnje', $nepredGod, 1);
    }

    if (table_exists($conn, 'budzet_stavke')) {
        $oldStavke = db_all($conn, "SELECT * FROM budzet_stavke WHERE budzet_id=? AND aktivna=1", 'i', [(int)$legacy['id']]);
        foreach ($oldStavke as $s) {
            $osnovica = 1;
            $obracun = $s['obracun'] ?? 'fiksno';
            if ($obracun === 'po_posebnom_delu') { $osnovica = (float)($z['broj_posebnih_delova'] ?? 0); }
            if ($obracun === 'po_m2') { $osnovica = (float)($z['povrsina_posebnih_delova'] ?? ($z['ukupna_povrsina_posebnih_delova'] ?? 0)); }
            if ($obracun === 'po_garaznom_mestu') { $osnovica = (float)($z['broj_garaznih_mesta'] ?? 0); }
            $mnozilac = (($s['ucestalost'] ?? '') === 'mesecno') ? 12 : 1;
            $godisnje = (float)($s['iznos'] ?? 0) * $osnovica * $mnozilac;
            $tip = (($s['vrsta'] ?? '') === 'priliv') ? 'priliv' : 'odliv';
            $naziv = trim($s['naziv'] ?? 'Stavka iz starog budžeta');
            if ($naziv !== '' && $godisnje != 0) {
                upsert_plan_stavka_by_name($conn, $planId, $tip, $naziv, 'Preneto iz v1 budžeta', 'godisnje', $godisnje, 0);
            }
        }
    }

    return db_one($conn, "SELECT * FROM finansijski_planovi WHERE id=?", 'i', [$planId]);
}

function upsert_plan_stavka_by_name($conn, $planId, $tip, $naziv, $grupa, $period, $iznos, $predefinisana = 0) {
    $existing = db_one($conn, "SELECT id FROM finansijski_plan_stavke WHERE plan_id=? AND tip=? AND naziv=? LIMIT 1", 'iss', [$planId, $tip, $naziv]);
    if ($existing) {
        $stmt = $conn->prepare("UPDATE finansijski_plan_stavke SET grupa=?, period=?, iznos=?, predefinisana=?, aktivna=1 WHERE id=?");
        $stmt->bind_param('ssdii', $grupa, $period, $iznos, $predefinisana, $existing['id']);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO finansijski_plan_stavke (plan_id, tip, naziv, grupa, period, iznos, predefinisana, aktivna) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param('issssdi', $planId, $tip, $naziv, $grupa, $period, $iznos, $predefinisana);
        $stmt->execute();
    }
}


function mesec_naziv($m) {
    $names = [1=>'Januar',2=>'Februar',3=>'Mart',4=>'April',5=>'Maj',6=>'Jun',7=>'Jul',8=>'Avgust',9=>'Septembar',10=>'Oktobar',11=>'Novembar',12=>'Decembar'];
    return $names[(int)$m] ?? '';
}

function mesec_kratko($m) {
    $names = [1=>'JAN',2=>'FEB',3=>'MAR',4=>'APR',5=>'MAJ',6=>'JUN',7=>'JUL',8=>'AVG',9=>'SEP',10=>'OKT',11=>'NOV',12=>'DEC'];
    return $names[(int)$m] ?? '';
}

function normalize_month($m, $default) {
    $m = (int)$m;
    if ($m < 1 || $m > 12) { return $default; }
    return $m;
}

function stavka_month_count($s) {
    $period = $s['period'] ?? 'godisnje';
    if ($period === 'jednokratno') { return 1; }
    if ($period === 'godisnje') { return 1; }
    $od = normalize_month($s['mesec_od'] ?? 1, 1);
    $do = normalize_month($s['mesec_do'] ?? 12, 12);
    if ($do < $od) { return 0; }
    return $do - $od + 1;
}

function stavka_active_in_month($s, $month) {
    $month = (int)$month;
    $period = $s['period'] ?? 'godisnje';
    $od = normalize_month($s['mesec_od'] ?? 1, 1);
    $do = normalize_month($s['mesec_do'] ?? 12, 12);
    if ($period === 'godisnje') { return $month === $od; }
    if ($period === 'jednokratno') { return $month === $od; }
    return $month >= $od && $month <= $do;
}

function stavka_osnovica($s, $metrics) {
    $osnov = $s['osnov'] ?? 'fiksno';
    if ($osnov === 'poseban_deo') { return (float)($metrics['brojDelova'] ?? 0); }
    if ($osnov === 'garazno_mesto') { return (float)($metrics['brojGaraza'] ?? 0); }
    if ($osnov === 'm2_posebni') { return (float)($metrics['povrsinaDelova'] ?? 0); }
    if ($osnov === 'm2_garaza') { return (float)($metrics['povrsinaGaraza'] ?? 0); }
    if ($osnov === 'm2_ukupno') { return (float)($metrics['ukupnaPovrsina'] ?? 0); }
    return 1.0;
}

function stavka_period_label($s) {
    $period = $s['period'] ?? 'godisnje';
    $od = normalize_month($s['mesec_od'] ?? 1, 1);
    $do = normalize_month($s['mesec_do'] ?? 12, 12);
    if ($period === 'mesecno') { return mesec_kratko($od) . '–' . mesec_kratko($do); }
    if ($period === 'jednokratno') { return mesec_kratko($od); }
    return 'Godišnje (' . mesec_kratko($od) . ')';
}

function stavka_osnov_label($osnov) {
    $labels = [
        'fiksno'=>'fiksno',
        'poseban_deo'=>'po posebnom delu',
        'garazno_mesto'=>'po garažnom mestu',
        'm2_posebni'=>'po m² posebnih delova',
        'm2_garaza'=>'po m² garaža',
        'm2_ukupno'=>'po ukupnoj m²',
    ];
    return $labels[$osnov] ?? $osnov;
}

function stavka_total($s, $metrics) {
    $iznos = (float)($s['iznos'] ?? 0);
    $osnovica = stavka_osnovica($s, $metrics);
    $period = $s['period'] ?? 'godisnje';
    if ($period === 'mesecno') { return $iznos * $osnovica * stavka_month_count($s); }
    return $iznos * $osnovica;
}

function stavka_month_value($s, $metrics, $month) {
    if (!stavka_active_in_month($s, $month)) { return 0; }
    $iznos = (float)($s['iznos'] ?? 0);
    return $iznos * stavka_osnovica($s, $metrics);
}

function stavka_formula($s, $metrics) {
    $base = stavka_osnovica($s, $metrics);
    $period = $s['period'] ?? 'godisnje';
    $months = stavka_month_count($s);
    $parts = [money_rs($s['iznos'] ?? 0)];
    $osnov = $s['osnov'] ?? 'fiksno';
    if ($osnov !== 'fiksno') { $parts[] = '× ' . number_format($base, 2, ',', '.'); }
    if ($period === 'mesecno') { $parts[] = '× ' . $months . ' mes.'; }
    return implode(' ', $parts);
}

function base_priliv_rows($plan, $metrics) {
    $p = fn($field, $default=0) => isset($plan[$field]) ? (float)$plan[$field] : $default;
    return [
        ['naziv'=>'Tekuće održavanje','osnov'=>'poseban_deo','osnovica'=>$metrics['brojDelova'] ?? 0,'iznos'=>$p('tekuce_po_delu'),'meseci'=>12,'total'=>($metrics['brojDelova'] ?? 0)*$p('tekuce_po_delu')*12],
        ['naziv'=>'Upravljanje','osnov'=>'poseban_deo','osnovica'=>$metrics['brojDelova'] ?? 0,'iznos'=>$p('upravljanje_po_delu'),'meseci'=>12,'total'=>($metrics['brojDelova'] ?? 0)*$p('upravljanje_po_delu')*12],
        ['naziv'=>'Tekuće održavanje garaža','osnov'=>'garazno_mesto','osnovica'=>$metrics['brojGaraza'] ?? 0,'iznos'=>$p('garaza_po_mestu'),'meseci'=>12,'total'=>($metrics['brojGaraza'] ?? 0)*$p('garaza_po_mestu')*12],
        ['naziv'=>'Investiciono održavanje','osnov'=>'m2_ukupno','osnovica'=>$metrics['ukupnaPovrsina'] ?? 0,'iznos'=>$p('investiciono_po_m2'),'meseci'=>12,'total'=>($metrics['ukupnaPovrsina'] ?? 0)*$p('investiciono_po_m2')*12],
    ];
}

function base_priliv_month_value($plan, $metrics, $month) {
    $sum = 0;
    foreach (base_priliv_rows($plan, $metrics) as $r) { $sum += (float)$r['osnovica'] * (float)$r['iznos']; }
    return $sum;
}

function finansijski_plan_summary($conn, $szId, $godina) {
    ensure_finansijski_plan_schema($conn);
    $z = db_one($conn, "SELECT * FROM stambene_zajednice WHERE id=?", 'i', [(int)$szId]);
    if (!$z) { return null; }
    $plan = get_or_create_finansijski_plan($conn, (int)$szId, (int)$godina);
    $plan = sync_finansijski_plan_from_v1_budzet($conn, $plan, $z, (int)$godina);

    $stavke = db_all($conn, "SELECT * FROM finansijski_plan_stavke WHERE plan_id=? AND aktivna=1 ORDER BY tip, predefinisana DESC, grupa, naziv", 'i', [(int)$plan['id']]);
    $brojDelova = get_building_metric($z, $conn, ['broj_posebnih_delova','broj_delova','broj_stanova'], 0);
    $brojGaraza = get_building_metric($z, $conn, ['broj_garaznih_mesta','broj_garaza'], 0);
    $povrsinaDelova = get_building_metric($z, $conn, ['ukupna_povrsina_posebnih_delova','povrsina_posebnih_delova','ukupna_povrsina'], 0);
    $povrsinaGaraza = get_building_metric($z, $conn, ['ukupna_povrsina_garaznih_mesta','povrsina_garaznih_mesta'], 0);
    $ukupnaPovrsina = $povrsinaDelova + $povrsinaGaraza;
    $metrics = compact('brojDelova','brojGaraza','povrsinaDelova','povrsinaGaraza','ukupnaPovrsina');

    $stanjeCol = first_existing_column($conn, 'stambene_zajednice', ['pocetno_stanje','pocetno_stanje_racuna','stanje_racuna'], null);
    $pocetnoStanje = $stanjeCol ? (float)($z[$stanjeCol] ?? 0) : 0;
    if (table_exists($conn, 'budzeti')) {
        $legacyBudzet = db_one($conn, "SELECT pocetno_stanje_racuna FROM budzeti WHERE sz_id=? AND godina=? ORDER BY (status='aktivan') DESC, id DESC LIMIT 1", 'ii', [(int)$szId, (int)$godina]);
        if ($legacyBudzet && isset($legacyBudzet['pocetno_stanje_racuna'])) {
            $pocetnoStanje = (float)$legacyBudzet['pocetno_stanje_racuna'];
        }
    }

    $basePrilivi = base_priliv_rows($plan, $metrics);
    $planiraniPriliv = 0;
    foreach ($basePrilivi as $r) { $planiraniPriliv += (float)$r['total']; }

    $dodatniPrilivi = 0;
    $planiraniOdlivi = 0;
    foreach ($stavke as $s) {
        $total = stavka_total($s, $metrics);
        if (($s['tip'] ?? '') === 'priliv') { $dodatniPrilivi += $total; }
        if (($s['tip'] ?? '') === 'odliv') { $planiraniOdlivi += $total; }
    }
    $planiraniPriliv += $dodatniPrilivi;
    $stepenNaplate = isset($plan['stepen_naplate']) ? (float)$plan['stepen_naplate'] : 100;
    $ocekivaniPriliv = $planiraniPriliv * ($stepenNaplate / 100);
    $nepredProc = isset($plan['nepredvidjeni_proc']) ? (float)$plan['nepredvidjeni_proc'] : 0;
    $nepredvidjeni = $planiraniOdlivi * ($nepredProc / 100);
    $ukupniOdlivi = $planiraniOdlivi + $nepredvidjeni;
    $saldoPlana = $ocekivaniPriliv - $ukupniOdlivi;
    $ocekivanoKrajGodine = $pocetnoStanje + $saldoPlana;

    $monthly = [];
    for ($m=1; $m<=12; $m++) {
        $priliv = base_priliv_month_value($plan, $metrics, $m);
        $odliv = 0;
        foreach ($stavke as $s) {
            $value = stavka_month_value($s, $metrics, $m);
            if (($s['tip'] ?? '') === 'priliv') { $priliv += $value; }
            if (($s['tip'] ?? '') === 'odliv') { $odliv += $value; }
        }
        $prilivOcekivani = $priliv * ($stepenNaplate / 100);
        // Nepredviđeni troškovi se prikazuju proporcionalno na svaki mesec u kome ima planiranih odliva.
        $nepredMesec = $odliv * ($nepredProc / 100);
        $monthly[$m] = [
            'mesec'=>$m,
            'naziv'=>mesec_kratko($m),
            'planirani_priliv'=>$priliv,
            'ocekivani_priliv'=>$prilivOcekivani,
            'odliv'=>$odliv + $nepredMesec,
            'saldo'=>$prilivOcekivani - ($odliv + $nepredMesec),
        ];
    }

    $today = new DateTime('today');
    $currentMonth = (int)$today->format('n');
    if ((int)$today->format('Y') < (int)$godina) { $untilMonth = 0; }
    elseif ((int)$today->format('Y') > (int)$godina) { $untilMonth = 12; }
    else { $untilMonth = $currentMonth; }
    $planiranoStanjeDanas = $pocetnoStanje;
    for ($m=1; $m<=$untilMonth; $m++) { $planiranoStanjeDanas += $monthly[$m]['saldo']; }

    return [
        'zgrada' => $z,
        'plan' => $plan,
        'stavke' => $stavke,
        'basePrilivi' => $basePrilivi,
        'metrics' => $metrics,
        'brojDelova' => $brojDelova,
        'brojGaraza' => $brojGaraza,
        'povrsinaDelova' => $povrsinaDelova,
        'povrsinaGaraza' => $povrsinaGaraza,
        'ukupnaPovrsina' => $ukupnaPovrsina,
        'pocetnoStanje' => $pocetnoStanje,
        'planiraniPriliv' => $planiraniPriliv,
        'ocekivaniPriliv' => $ocekivaniPriliv,
        'dodatniPrilivi' => $dodatniPrilivi,
        'planiraniOdlivi' => $planiraniOdlivi,
        'nepredvidjeni' => $nepredvidjeni,
        'ukupniOdlivi' => $ukupniOdlivi,
        'saldoPlana' => $saldoPlana,
        'ocekivanoKrajGodine' => $ocekivanoKrajGodine,
        'planiranoStanjeDanas' => $planiranoStanjeDanas,
        'monthly' => $monthly,
        'untilMonth' => $untilMonth,
    ];
}
