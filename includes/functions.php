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
        investiciono_garaza_po_m2 DECIMAL(12,2) NOT NULL DEFAULT 0,
        stepen_naplate DECIMAL(5,2) NOT NULL DEFAULT 100,
        nepredvidjeni_proc DECIMAL(5,2) NOT NULL DEFAULT 0,
        mesec_pocetka TINYINT NOT NULL DEFAULT 1,
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

    $res = $conn->query("SHOW COLUMNS FROM finansijski_planovi LIKE 'investiciono_garaza_po_m2'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE finansijski_planovi ADD COLUMN investiciono_garaza_po_m2 DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER investiciono_po_m2");
    }
    $res = $conn->query("SHOW COLUMNS FROM finansijski_planovi LIKE 'mesec_pocetka'");
    if ($res && $res->num_rows === 0) {
        $conn->query("ALTER TABLE finansijski_planovi ADD COLUMN mesec_pocetka TINYINT NOT NULL DEFAULT 1 AFTER nepredvidjeni_proc");
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
        ['odliv','Bankarski troškovi','Opšti troškovi','mesecno',0,1],
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

function numeric_value($value) {
    if ($value === null || $value === '') { return 0.0; }
    if (is_string($value)) { $value = str_replace(',', '.', $value); }
    return (float)$value;
}

function get_building_metric($z, $conn, $candidates, $default = 0) {
    foreach ($candidates as $candidate) {
        if (array_key_exists($candidate, $z)) {
            return numeric_value($z[$candidate]);
        }
    }
    return (float)$default;
}

function get_building_count_metric($z, $candidates, $default = 0) {
    // Broj posebnih delova / garaža mora biti realan broj komada.
    // Ako u starim podacima greškom u toj koloni stoji površina (npr. 1058 m²), ne sme se koristiti kao broj stanova.
    foreach ($candidates as $candidate) {
        if (array_key_exists($candidate, $z)) {
            $v = numeric_value($z[$candidate]);
            if ($v > 0 && $v <= 500) { return $v; }
        }
    }
    return (float)$default;
}

function get_building_area_metric($z, $candidates, $default = 0) {
    foreach ($candidates as $candidate) {
        if (array_key_exists($candidate, $z)) {
            $v = numeric_value($z[$candidate]);
            if ($v > 0) { return $v; }
        }
    }
    return (float)$default;
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
        $investGaraza = (float)($legacy['investiciono_garaza_po_m2'] ?? 0);
        $naplata = (float)($legacy['procenat_naplate'] ?? 100);
        $stmt = $conn->prepare("UPDATE finansijski_planovi SET tekuce_po_delu=?, upravljanje_po_delu=?, garaza_po_mestu=?, investiciono_po_m2=?, investiciono_garaza_po_m2=?, stepen_naplate=? WHERE id=?");
        $stmt->bind_param('ddddddi', $tekuce, $upravljanje, $garaza, $invest, $investGaraza, $naplata, $plan['id']);
        $stmt->execute();
    }

    $planId = (int)$plan['id'];

    // Bankarski troškovi ostaju mesečni: finansijska projekcija ih računa od meseca početka plana.
    $bankarskiMes = (float)($legacy['bankarski_troskovi_mesecno'] ?? 0);
    if ($bankarskiMes > 0) {
        upsert_plan_stavka_by_name($conn, $planId, 'odliv', 'Bankarski troškovi', 'Opšti troškovi', 'mesecno', $bankarskiMes, 1, 'fiksno', 1, 12);
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

    // Stavke iz starog v1 budžeta migriraju se samo jednom.
    // Važno: ne smeju se ponovo upisivati/aktivirati pri svakom otvaranju plana,
    // jer bi to poništilo korisnikovu izmenu ili uklanjanje prenete stavke.
    if (table_exists($conn, 'budzet_stavke')) {
        $vecMigrirano = db_one($conn,
            "SELECT id FROM finansijski_plan_stavke WHERE plan_id=? AND grupa='Preneto iz v1 budžeta' LIMIT 1",
            'i', [$planId]
        );
        if (!$vecMigrirano) {
            $oldStavke = db_all($conn, "SELECT * FROM budzet_stavke WHERE budzet_id=? AND aktivna=1", 'i', [(int)$legacy['id']]);
            foreach ($oldStavke as $s) {
                $obracun = $s['obracun'] ?? 'fiksno';
                $osnov = 'fiksno';
                if ($obracun === 'po_posebnom_delu') { $osnov = 'poseban_deo'; }
                if ($obracun === 'po_garaznom_mestu') { $osnov = 'garazno_mesto'; }
                if ($obracun === 'po_m2') { $osnov = 'm2_posebni'; }
                $period = (($s['ucestalost'] ?? '') === 'mesecno') ? 'mesecno' : 'godisnje';
                $tip = (($s['vrsta'] ?? '') === 'priliv') ? 'priliv' : 'odliv';
                $naziv = trim($s['naziv'] ?? 'Stavka iz starog budžeta');
                $iznos = (float)($s['iznos'] ?? 0);
                if ($naziv !== '' && $iznos != 0) {
                    upsert_plan_stavka_by_name($conn, $planId, $tip, $naziv, 'Preneto iz v1 budžeta', $period, $iznos, 0, $osnov, 1, ($period === 'mesecno' ? 12 : 1));
                }
            }
        }
    }

    return db_one($conn, "SELECT * FROM finansijski_planovi WHERE id=?", 'i', [$planId]);
}

function upsert_plan_stavka_by_name($conn, $planId, $tip, $naziv, $grupa, $period, $iznos, $predefinisana = 0, $osnov = 'fiksno', $mesecOd = 1, $mesecDo = 12) {
    $existing = db_one($conn, "SELECT id FROM finansijski_plan_stavke WHERE plan_id=? AND tip=? AND naziv=? LIMIT 1", 'iss', [$planId, $tip, $naziv]);
    if ($period === 'godisnje' || $period === 'jednokratno') { $mesecDo = $mesecOd; }
    if ($existing) {
        $stmt = $conn->prepare("UPDATE finansijski_plan_stavke SET grupa=?, period=?, osnov=?, iznos=?, mesec_od=?, mesec_do=?, predefinisana=?, aktivna=1 WHERE id=?");
        $stmt->bind_param('sssdiiii', $grupa, $period, $osnov, $iznos, $mesecOd, $mesecDo, $predefinisana, $existing['id']);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO finansijski_plan_stavke (plan_id, tip, naziv, grupa, period, osnov, iznos, mesec_od, mesec_do, predefinisana, aktivna) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param('isssssdiii', $planId, $tip, $naziv, $grupa, $period, $osnov, $iznos, $mesecOd, $mesecDo, $predefinisana);
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

function stavka_total_od_meseca($s, $metrics, $mesecPocetka=1) {
    $total = 0.0;
    $mesecPocetka = max(1, min(12, (int)$mesecPocetka));
    for ($m=$mesecPocetka; $m<=12; $m++) { $total += stavka_month_value($s, $metrics, $m); }
    return $total;
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
    $pocetak = max(1, min(12, (int)($plan['mesec_pocetka'] ?? 1)));
    $meseci = 13 - $pocetak;
    return [
        ['naziv'=>'Tekuće održavanje','osnov'=>'poseban_deo','osnovica'=>$metrics['brojDelova'] ?? 0,'iznos'=>$p('tekuce_po_delu'),'meseci'=>$meseci,'mesec_od'=>$pocetak,'total'=>($metrics['brojDelova'] ?? 0)*$p('tekuce_po_delu')*$meseci],
        ['naziv'=>'Upravljanje','osnov'=>'poseban_deo','osnovica'=>$metrics['brojDelova'] ?? 0,'iznos'=>$p('upravljanje_po_delu'),'meseci'=>$meseci,'mesec_od'=>$pocetak,'total'=>($metrics['brojDelova'] ?? 0)*$p('upravljanje_po_delu')*$meseci],
        ['naziv'=>'Tekuće održavanje garaža','osnov'=>'garazno_mesto','osnovica'=>$metrics['brojGaraza'] ?? 0,'iznos'=>$p('garaza_po_mestu'),'meseci'=>$meseci,'mesec_od'=>$pocetak,'total'=>($metrics['brojGaraza'] ?? 0)*$p('garaza_po_mestu')*$meseci],
        ['naziv'=>'Investiciono održavanje posebnih delova','osnov'=>'m2_posebni','osnovica'=>$metrics['povrsinaDelova'] ?? 0,'iznos'=>$p('investiciono_po_m2'),'meseci'=>$meseci,'mesec_od'=>$pocetak,'total'=>($metrics['povrsinaDelova'] ?? 0)*$p('investiciono_po_m2')*$meseci],
        ['naziv'=>'Investiciono održavanje garažnog prostora','osnov'=>'m2_garaza','osnovica'=>$metrics['povrsinaGaraza'] ?? 0,'iznos'=>$p('investiciono_garaza_po_m2'),'meseci'=>$meseci,'mesec_od'=>$pocetak,'total'=>($metrics['povrsinaGaraza'] ?? 0)*$p('investiciono_garaza_po_m2')*$meseci],
    ];
}

function base_priliv_month_value($plan, $metrics, $month) {
    $pocetak = max(1, min(12, (int)($plan['mesec_pocetka'] ?? 1)));
    if ((int)$month < $pocetak) return 0.0;
    $sum = 0;
    foreach (base_priliv_rows($plan, $metrics) as $r) { $sum += (float)$r['osnovica'] * (float)$r['iznos']; }
    return $sum;
}

function finansijski_plan_summary($conn, $szId, $godina, $ukljuciProgram = true) {
    ensure_finansijski_plan_schema($conn);
    $z = db_one($conn, "SELECT * FROM stambene_zajednice WHERE id=?", 'i', [(int)$szId]);
    if (!$z) { return null; }
    $plan = get_or_create_finansijski_plan($conn, (int)$szId, (int)$godina);
    $plan = sync_finansijski_plan_from_v1_budzet($conn, $plan, $z, (int)$godina);

    // Program održavanja se više NE kopira u finansijski_plan_stavke.
    // Gasimo eventualne stare automatske redove iz prethodne verzije da ne prave duplikate.
    $stmtLegacyProgram = $conn->prepare("UPDATE finansijski_plan_stavke SET aktivna=0 WHERE plan_id=? AND izvor='program_odrzavanja'");
    if ($stmtLegacyProgram) {
        $pid = (int)$plan['id'];
        $stmtLegacyProgram->bind_param('i', $pid);
        $stmtLegacyProgram->execute();
    }
    $stavke = db_all($conn, "SELECT * FROM finansijski_plan_stavke WHERE plan_id=? AND aktivna=1 AND (tip='priliv' OR naziv IN ('Profesionalni upravnik','Bankarski troškovi')) ORDER BY tip, predefinisana DESC, grupa, naziv", 'i', [(int)$plan['id']]);
    $brojDelova = get_building_count_metric($z, ['broj_posebnih_delova','broj_delova','broj_stanova_lokala','broj_stanova_i_lokala','broj_stanova'], 0);
    $brojGaraza = get_building_count_metric($z, ['broj_garaznih_mesta','broj_garaza','garazna_mesta'], 0);
    $povrsinaDelova = get_building_area_metric($z, ['ukupna_povrsina_posebnih_delova','povrsina_posebnih_delova','povrsina_stanova_lokala','ukupna_povrsina_stanova_lokala'], 0);
    $povrsinaGaraza = get_building_area_metric($z, ['ukupna_povrsina_garaznih_mesta','povrsina_garaznih_mesta','povrsina_garaza'], 0);
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

    $planPocetak = max(1, min(12, (int)($plan['mesec_pocetka'] ?? 1)));
    $dodatniPrilivi = 0;
    $planiraniOdlivi = 0;
    foreach ($stavke as $s) {
        $total = 0.0;
        for ($m=$planPocetak; $m<=12; $m++) {
            $total += stavka_month_value($s, $metrics, $m);
        }
        if (($s['tip'] ?? '') === 'priliv') { $dodatniPrilivi += $total; }
        if (($s['tip'] ?? '') === 'odliv') { $planiraniOdlivi += $total; }
    }
    $planiraniPriliv += $dodatniPrilivi;
    $programOdlivi = $ukljuciProgram ? program_mesecni_odlivi($conn, $szId, $godina) : array_fill(1,12,0.0);
    $programUkupno = 0.0;
    for ($m=$planPocetak; $m<=12; $m++) $programUkupno += (float)($programOdlivi[$m] ?? 0);
    $planiraniOdlivi += $programUkupno;
    $stepenNaplate = isset($plan['stepen_naplate']) ? (float)$plan['stepen_naplate'] : 100;
    $ocekivaniPriliv = $planiraniPriliv * ($stepenNaplate / 100);
    $nepredProc = isset($plan['nepredvidjeni_proc']) ? (float)$plan['nepredvidjeni_proc'] : 0;
    $nepredvidjeni = $planiraniOdlivi * ($nepredProc / 100);
    $ukupniOdlivi = $planiraniOdlivi + $nepredvidjeni;
    $saldoPlana = $ocekivaniPriliv - $ukupniOdlivi;
    $ocekivanoKrajGodine = $pocetnoStanje + $saldoPlana;

    // Jedinstvena cash-flow projekcija. 'saldo' je promena u mesecu,
    // a 'stanje' je kumulativno očekivano stanje računa na kraju tog meseca.
    $monthly = [];
    $kumulativnoStanje = $pocetnoStanje;
    for ($m=1; $m<=12; $m++) {
        $priliv = 0.0;
        $odliv = 0.0;
        if ($m >= $planPocetak) {
            $priliv = base_priliv_month_value($plan, $metrics, $m);
            foreach ($stavke as $s) {
                $value = stavka_month_value($s, $metrics, $m);
                if (($s['tip'] ?? '') === 'priliv') { $priliv += $value; }
                if (($s['tip'] ?? '') === 'odliv') { $odliv += $value; }
            }
            if ($ukljuciProgram) { $odliv += (float)($programOdlivi[$m] ?? 0); }
        }
        $prilivOcekivani = $priliv * ($stepenNaplate / 100);
        // Nepredviđeni troškovi se prikazuju proporcionalno na svaki mesec u kome ima planiranih odliva.
        $nepredMesec = $odliv * ($nepredProc / 100);
        $mesecniSaldo = $prilivOcekivani - ($odliv + $nepredMesec);
        $kumulativnoStanje += $mesecniSaldo;
        $monthly[$m] = [
            'mesec'=>$m,
            'naziv'=>mesec_kratko($m),
            'planirani_priliv'=>$priliv,
            'ocekivani_priliv'=>$prilivOcekivani,
            'odliv'=>$odliv + $nepredMesec,
            'saldo'=>$mesecniSaldo,
            'stanje'=>$kumulativnoStanje,
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
        'programOdlivi' => $programOdlivi,
        'programUkupno' => $programUkupno,
        'nepredvidjeni' => $nepredvidjeni,
        'ukupniOdlivi' => $ukupniOdlivi,
        'saldoPlana' => $saldoPlana,
        'ocekivanoKrajGodine' => $ocekivanoKrajGodine,
        'planiranoStanjeDanas' => $planiranoStanjeDanas,
        'monthly' => $monthly,
        'untilMonth' => $untilMonth,
        'planPocetak' => $planPocetak,
    ];
}

/* ===== Program održavanja v2.2 ===== */
function ensure_program_odrzavanja_schema($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS program_odrzavanja_stavke (
        id INT AUTO_INCREMENT PRIMARY KEY, sz_id INT NOT NULL, naziv VARCHAR(255) NOT NULL,
        kategorija VARCHAR(100) NOT NULL DEFAULT 'Ostalo', opis TEXT NULL,
        ucestalost_meseci INT NOT NULL DEFAULT 12, prvi_datum DATE NOT NULL,
        izvodjac_id INT NULL, ponuda_id INT NULL, aktivna TINYINT(1) NOT NULL DEFAULT 1,
        prioritet ENUM('kriticno','visoko','srednje','nisko') NOT NULL DEFAULT 'srednje',
        pocetni_mesec TINYINT NOT NULL DEFAULT 1, najraniji_mesec TINYINT NOT NULL DEFAULT 1, krajnji_mesec TINYINT NOT NULL DEFAULT 12,
        nacin_placanja ENUM('po_terminu','jednokratno','rate') NOT NULL DEFAULT 'po_terminu',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach ([
        'prioritet'=>"ALTER TABLE program_odrzavanja_stavke ADD COLUMN prioritet ENUM('kriticno','visoko','srednje','nisko') NOT NULL DEFAULT 'srednje' AFTER aktivna",
        'pocetni_mesec'=>"ALTER TABLE program_odrzavanja_stavke ADD COLUMN pocetni_mesec TINYINT NOT NULL DEFAULT 1 AFTER prioritet",
        'najraniji_mesec'=>"ALTER TABLE program_odrzavanja_stavke ADD COLUMN najraniji_mesec TINYINT NOT NULL DEFAULT 1 AFTER prioritet",
        'krajnji_mesec'=>"ALTER TABLE program_odrzavanja_stavke ADD COLUMN krajnji_mesec TINYINT NOT NULL DEFAULT 12 AFTER najraniji_mesec"
        ,'nacin_placanja'=>"ALTER TABLE program_odrzavanja_stavke ADD COLUMN nacin_placanja ENUM('po_terminu','jednokratno','rate') NOT NULL DEFAULT 'po_terminu' AFTER krajnji_mesec"
        ,'izvor_cene'=>"ALTER TABLE program_odrzavanja_stavke ADD COLUMN izvor_cene ENUM('ponuda','cenovnik','procena') NULL AFTER nacin_placanja"
        ,'cenovnik_stavka_id'=>"ALTER TABLE program_odrzavanja_stavke ADD COLUMN cenovnik_stavka_id INT NULL AFTER izvor_cene"
        ,'jedinicna_cena'=>"ALTER TABLE program_odrzavanja_stavke ADD COLUMN jedinicna_cena DECIMAL(12,2) NULL AFTER cenovnik_stavka_id"
        ,'kolicina'=>"ALTER TABLE program_odrzavanja_stavke ADD COLUMN kolicina DECIMAL(12,3) NOT NULL DEFAULT 1 AFTER jedinicna_cena"
        ,'ukupna_cena'=>"ALTER TABLE program_odrzavanja_stavke ADD COLUMN ukupna_cena DECIMAL(12,2) NULL AFTER kolicina"
    ] as $c=>$sql) if (!has_column($conn,'program_odrzavanja_stavke',$c)) $conn->query($sql);
    // Stare stavke sa ponudom dobijaju eksplicitni izvor cene.
    $conn->query("UPDATE program_odrzavanja_stavke SET izvor_cene='ponuda' WHERE ponuda_id IS NOT NULL AND ponuda_id>0 AND (izvor_cene IS NULL OR izvor_cene='')");

    $conn->query("CREATE TABLE IF NOT EXISTS program_odrzavanja_termini (
        id INT AUTO_INCREMENT PRIMARY KEY, stavka_id INT NOT NULL, datum DATE NOT NULL,
        status ENUM('planirano','izvrseno','propusteno','otkazano') NOT NULL DEFAULT 'planirano',
        predlozen TINYINT(1) NOT NULL DEFAULT 0, napomena TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_stavka_datum(stavka_id,datum)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!has_column($conn,'program_odrzavanja_termini','predlozen')) $conn->query("ALTER TABLE program_odrzavanja_termini ADD COLUMN predlozen TINYINT(1) NOT NULL DEFAULT 0 AFTER status");

    $conn->query("CREATE TABLE IF NOT EXISTS program_odrzavanje_ponude (
        id INT AUTO_INCREMENT PRIMARY KEY, program_id INT NOT NULL, ponuda_id INT NOT NULL, izabrana TINYINT(1) DEFAULT 0,
        UNIQUE KEY uniq_program_ponuda(program_id,ponuda_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS program_odrzavanja_placanja (
        id INT AUTO_INCREMENT PRIMARY KEY, program_id INT NOT NULL, ponuda_id INT NULL,
        datum_placanja DATE NOT NULL, iznos DECIMAL(12,2) NOT NULL DEFAULT 0, procenat DECIMAL(7,3) NULL,
        napomena VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_program_datum(program_id,datum_placanja), INDEX idx_ponuda(ponuda_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Plan plaćanja mora raditi i kada cena dolazi iz cenovnika ili procene, bez ponude.
    $conn->query("ALTER TABLE program_odrzavanja_placanja MODIFY ponuda_id INT NULL");

    // Jednokratna, bezbedna migracija starog programa ako nova tabela još nema stavke.
    if (table_exists($conn,'program_odrzavanja')) {
        $cnt=db_one($conn,"SELECT COUNT(*) c FROM program_odrzavanja_stavke");
        if ((int)($cnt['c']??0)===0) {
            $old=db_all($conn,"SELECT * FROM program_odrzavanja ORDER BY id");
            $st=$conn->prepare("INSERT INTO program_odrzavanja_stavke(sz_id,naziv,kategorija,opis,ucestalost_meseci,prvi_datum,aktivna,prioritet,najraniji_mesec,krajnji_mesec) VALUES(?,?,?,?,?,?,?,?,?,?)");
            foreach($old as $o){ $m=max(1,min(12,(int)($o['mesec']??1))); $datum=sprintf('%04d-%02d-15',current_year(),$m); $naz=$o['aktivnost']??'Aktivnost'; $kat=$o['kategorija']?:'Održavanje'; $opis=$o['napomena']??''; $uc=isset($o['ucestalost_broj'])&&$o['ucestalost_broj']?(int)$o['ucestalost_broj']:12; $akt=empty($o['zavrsena'])?1:0; $pr=!empty($o['obavezna'])?'kriticno':'srednje'; $do=12; $st->bind_param('isssisisii',$o['sz_id'],$naz,$kat,$opis,$uc,$datum,$akt,$pr,$m,$do); $st->execute(); }
        }
    }
}

function program_ponuda_iznos($conn, $ponudaId) {
    $p = db_one($conn, "SELECT iznos FROM ponude WHERE id=?", 'i', [(int)$ponudaId]);
    if (!$p) return 0.0;
    if ((float)($p['iznos'] ?? 0) > 0) return (float)$p['iznos'];
    $r = db_one($conn, "SELECT COALESCE(SUM(kolicina*cena),0) ukupno FROM ponuda_stavke WHERE ponuda_id=? AND aktivna=1", 'i', [(int)$ponudaId]);
    return (float)($r['ukupno'] ?? 0);
}

/** Vraća snapshot cenu stavke programa, bez obzira da li potiče iz ponude, cenovnika ili procene. */
/**
 * Za cenovničke jedinice koje predstavljaju komad konkretne opreme
 * (npr. aparat, hidrant, lift) vraća evidentiranu aktivnu količinu iz zgrade.
 * Za ostale jedinice vraća 0, pa korisnik količinu unosi ručno.
 */
function program_kolicina_iz_opreme($conn, $szId, $elementId, $jedinicaOznaka='') {
    // Količina se određuje prema elementu zgrade, a ne prema oznaci jedinice cenovnika.
    // Ovo je važno jer kontrola hidranta/PP aparata može u cenovniku biti izražena i kao "kom".
    $el = db_one($conn, "SELECT naziv, koristi_kolicinu FROM sifarnik_elemenata WHERE id=? AND aktivan=1", 'i', [(int)$elementId]);
    if (!$el || (int)($el['koristi_kolicinu'] ?? 0)!==1) return 0.0;
    $r = db_one($conn,
        "SELECT COALESCE(SUM(kolicina),0) kolicina FROM oprema_zgrade WHERE sz_id=? AND element_id=? AND aktivna=1",
        'ii', [(int)$szId, (int)$elementId]
    );
    return max(0.0, (float)($r['kolicina'] ?? 0));
}

function program_normalize_text($text) {
    $text = mb_strtolower((string)$text, 'UTF-8');
    $map = ['č'=>'c','ć'=>'c','ž'=>'z','š'=>'s','đ'=>'dj'];
    $text = strtr($text,$map);
    $text = preg_replace('/[^a-z0-9]+/u',' ', $text);
    return trim(preg_replace('/\s+/',' ', $text));
}

function program_element_family($text) {
    $t = program_normalize_text($text);
    $families = [
        'pp_aparat'=>['pp aparat','protivpozarni aparat','aparat za gasenje'],
        'hidrant'=>['hidrant','hidrantska'],
        'lift'=>['lift','dizalo'],
        'dojava_pozara'=>['dojava pozara','dojavu pozara'],
        'co'=>['co detek','ugljen monoksid'],
        'gromobran'=>['gromobran'],
        'interfon'=>['interfon'],
        'video'=>['video nadzor','videonadzor'],
        'kontrola_pristupa'=>['kontrola pristupa','elektronska brava'],
        'krov'=>['krov'],
        'oluci'=>['oluk','oluc'],
        'fasada'=>['fasad'],
        'vrata'=>['vrata','kapija'],
        'rasveta'=>['rasvet','svetil'],
        'ciscenje'=>['ciscenje','higijen'],
    ];
    foreach($families as $family=>$needles){ foreach($needles as $n){ if(strpos($t,$n)!==false) return $family; } }
    return '';
}

function program_activity_family($text) {
    $t=program_normalize_text($text);
    $families=[
        'kontrola'=>['kontrol','pregled','ispitiv','provera'],
        'servis'=>['servis','odrzavanje'],
        'popravka'=>['poprav','sanacij'],
        'zamena'=>['zamen'],
        'ciscenje'=>['cisc','higijen'],
    ];
    foreach($families as $family=>$needles){ foreach($needles as $n){ if(strpos($t,$n)!==false) return $family; } }
    return '';
}

/** Da li je cenovnik/ponuda logičan izbor za konkretnu stavku programa. */
function program_price_candidate_matches($stavka, $candidateText, $candidateElement='', $candidateActivity='') {
    $programText = trim(($stavka['naziv']??'').' '.($stavka['kategorija']??'').' '.($stavka['opis']??''));
    $pf = program_element_family($programText);
    $cf = program_element_family(trim($candidateElement.' '.$candidateText));
    if ($pf && $cf && $pf !== $cf) return false;

    $pa = program_activity_family($programText);
    $ca = program_activity_family(trim($candidateActivity.' '.$candidateText));
    // Kontrola/pregled/ispitivanje su jedna kompatibilna grupa; servis i popravka se ne nude za kontrolu.
    if ($pa && $ca && $pa !== $ca) return false;

    if ($pf && $cf === $pf) return true;

    $stop=['redovan','redovni','redovna','kontrolni','kontrola','pregled','servis','stavka','radovi','zgrade','zgrada','mreze','sistema','sistem'];
    $a=array_values(array_filter(explode(' ',program_normalize_text($programText)),fn($x)=>strlen($x)>=4 && !in_array($x,$stop,true)));
    $b=program_normalize_text(trim($candidateText.' '.$candidateElement.' '.$candidateActivity));
    foreach($a as $token){ if(strpos($b,$token)!==false) return true; }
    return false;
}

function program_stavka_cena($conn, $stavka) {
    $izvor = $stavka['izvor_cene'] ?? null;
    if (!$izvor && !empty($stavka['ponuda_id'])) $izvor = 'ponuda'; // kompatibilnost sa starim podacima
    if ($izvor === 'ponuda' && !empty($stavka['ponuda_id'])) return program_ponuda_iznos($conn,(int)$stavka['ponuda_id']);
    if (in_array($izvor,['cenovnik','procena'],true)) return max(0.0,(float)($stavka['ukupna_cena'] ?? 0));
    return 0.0;
}

function program_stavka_ima_cenu($stavka) {
    return (float)($stavka['planirani_iznos'] ?? 0) > 0;
}

function program_izvor_cene_label($stavka) {
    $izvor=$stavka['izvor_cene'] ?? (!empty($stavka['ponuda_id'])?'ponuda':null);
    if($izvor==='ponuda') return 'Ponuda';
    if($izvor==='cenovnik') return 'Cenovnik';
    if($izvor==='procena') return 'Procena / ručni iznos';
    return 'Cena nije definisana';
}

function program_stavke_sa_cenama($conn, $szId, $godina=null) {
    ensure_program_odrzavanja_schema($conn);
    $izcol=first_existing_column($conn,'izvodjaci',['naziv','ime','naziv_firme','firma'],'naziv');
    $rows = db_all($conn, "SELECT s.*, p.naziv ponuda_naziv, p.izvodjac_id ponuda_izvodjac_id,
            COALESCE(i.`$izcol`, ci.`$izcol`) izvodjac_naziv,
            c.naziv cenovnik_naziv, cs.cena cenovnik_aktuelna_cena,
            se.naziv cenovnik_element, sa.naziv cenovnik_aktivnost,
            oj.oznaka cenovnik_jedinica
        FROM program_odrzavanja_stavke s
        LEFT JOIN ponude p ON p.id=s.ponuda_id
        LEFT JOIN izvodjaci i ON i.id=COALESCE(s.izvodjac_id,p.izvodjac_id)
        LEFT JOIN cenovnik_stavke cs ON cs.id=s.cenovnik_stavka_id
        LEFT JOIN cenovnici c ON c.id=cs.cenovnik_id
        LEFT JOIN izvodjaci ci ON ci.id=c.izvodjac_id
        LEFT JOIN sifarnik_elemenata se ON se.id=cs.element_id
        LEFT JOIN sifarnik_aktivnosti sa ON sa.id=cs.aktivnost_id
        LEFT JOIN obracunske_jedinice oj ON oj.id=cs.jedinica_id
        WHERE s.sz_id=? AND s.aktivna=1
        ORDER BY FIELD(s.prioritet,'kriticno','visoko','srednje','nisko'), s.pocetni_mesec, s.najraniji_mesec, s.id", 'i', [(int)$szId]);
    foreach ($rows as &$r) {
        if (empty($r['izvor_cene']) && !empty($r['ponuda_id'])) $r['izvor_cene']='ponuda';
        $r['planirani_iznos'] = program_stavka_cena($conn,$r);
        if (($r['izvor_cene']??'')==='cenovnik') {
            $r['izvor_cene_naziv'] = trim(($r['cenovnik_naziv']??'Cenovnik').' · '.($r['cenovnik_aktivnost']??'').' / '.($r['cenovnik_element']??''),' ·/');
        } elseif (($r['izvor_cene']??'')==='procena') {
            $r['izvor_cene_naziv']='Procena / ručni iznos';
        } else {
            $r['izvor_cene_naziv']=$r['ponuda_naziv']??'Izabrana ponuda';
        }
    }
    unset($r);
    return $rows;
}

function program_termini_stavke($conn, $stavkaId, $godina) {
    return db_all($conn,
        "SELECT * FROM program_odrzavanja_termini WHERE stavka_id=? AND YEAR(datum)=? AND status IN ('planirano','izvrseno') ORDER BY datum,id",
        'ii', [(int)$stavkaId, (int)$godina]
    );
}

function program_placanja_stavke($conn, $programId, $godina=null) {
    ensure_program_odrzavanja_schema($conn);
    if ($godina === null) {
        return db_all($conn, "SELECT * FROM program_odrzavanja_placanja WHERE program_id=? ORDER BY datum_placanja,id", 'i', [(int)$programId]);
    }
    return db_all($conn, "SELECT * FROM program_odrzavanja_placanja WHERE program_id=? AND YEAR(datum_placanja)=? ORDER BY datum_placanja,id", 'ii', [(int)$programId,(int)$godina]);
}

function program_plan_placanja_status($conn, $stavka, $godina=null) {
    $cena = (float)($stavka['planirani_iznos'] ?? program_stavka_cena($conn,$stavka));
    $nacin = $stavka['nacin_placanja'] ?? 'po_terminu';
    if ($cena <= 0) {
        return ['status'=>'bez_cene','kompletno'=>false,'nacin'=>$nacin,'ukupno'=>0.0,'rasporedjeno'=>0.0,'preostalo'=>0.0,'rate'=>[],'sve_rate'=>[]];
    }
    if ($nacin === 'po_terminu') {
        return ['status'=>'kompletno','kompletno'=>true,'nacin'=>$nacin,'ukupno'=>$cena,'rasporedjeno'=>$cena,'preostalo'=>0.0,'rate'=>[],'sve_rate'=>[]];
    }
    $rate = program_placanja_stavke($conn,(int)$stavka['id'],$godina);
    $sveRate = $godina===null ? $rate : program_placanja_stavke($conn,(int)$stavka['id'],null);
    $rasporedjeno = 0.0; foreach($sveRate as $r) $rasporedjeno += (float)$r['iznos'];
    $preostalo = max(0.0,$cena-$rasporedjeno);
    $razlika = abs($rasporedjeno-$cena);
    $minBroj = $nacin==='rate' ? 2 : 1;
    $kompletno = count($sveRate)>=$minBroj && $razlika < 0.01;
    return ['status'=>$kompletno?'kompletno':'nepotpuno','kompletno'=>$kompletno,'nacin'=>$nacin,'ukupno'=>$cena,'rasporedjeno'=>$rasporedjeno,'preostalo'=>$preostalo,'rate'=>$rate,'sve_rate'=>$sveRate];
}

function program_mesecni_odlivi($conn, $szId, $godina) {
    $out=array_fill(1,12,0.0);
    foreach(program_stavke_sa_cenama($conn,$szId,$godina) as $s) {
        $cena=(float)$s['planirani_iznos'];
        if ($cena<=0) continue;
        $nacin=$s['nacin_placanja'] ?? 'po_terminu';
        if ($nacin==='po_terminu') {
            $pocetakStavke=max(1,min(12,(int)($s['pocetni_mesec'] ?? 1)));
            foreach(program_termini_stavke($conn,(int)$s['id'],(int)$godina) as $t) {
                $m=(int)date('n',strtotime($t['datum']));
                if($m>=$pocetakStavke && $m<=12) $out[$m]+=$cena;
            }
        } else {
            foreach(program_placanja_stavke($conn,(int)$s['id'],(int)$godina) as $r) {
                $m=(int)date('n',strtotime($r['datum_placanja']));
                if($m>=1 && $m<=12) $out[$m]+=(float)$r['iznos'];
            }
        }
    }
    return $out;
}

function program_planirani_broj_izvrsenja($stavka, $godina, $planPocetak=1) {
    $godina = (int)$godina;
    $pocetakStavke = max(1, min(12, (int)($stavka['pocetni_mesec'] ?? 1)));
    $od = max((int)$planPocetak, $pocetakStavke, max(1, min(12, (int)($stavka['najraniji_mesec'] ?? 1))));
    $do = max($od, min(12, (int)($stavka['krajnji_mesec'] ?? 12)));
    $interval = max(1, min(12, (int)($stavka['ucestalost_meseci'] ?? 12)));

    $tz = new DateTimeZone('Europe/Belgrade');
    $sada = new DateTimeImmutable('now', $tz);
    $tekucaGodina = (int)$sada->format('Y');
    $tekuciMesec = (int)$sada->format('n');
    if ($godina < $tekucaGodina) return 0;
    if ($godina === $tekucaGodina) $od = max($od, $tekuciMesec);
    if ($od > $do) return 0;

    // Koliko izvršenja pripada preostalom delu dozvoljenog perioda.
    // Za mesečnu stavku: broj meseci od sada/najranijeg meseca do krajnjeg meseca.
    return (int)floor(($do - $od) / $interval) + 1;
}

function program_godisnji_iznos_stavke($conn, $stavkaId, $godina, $cenaPoTerminu) {
    $stavka = db_one($conn, "SELECT * FROM program_odrzavanja_stavke WHERE id=? LIMIT 1", 'i', [(int)$stavkaId]);
    $plan = $stavka ? db_one($conn, "SELECT * FROM finansijski_planovi WHERE sz_id=? AND godina=? LIMIT 1", 'ii', [(int)$stavka['sz_id'], (int)$godina]) : null;
    $planPocetak = max(1, min(12, (int)($plan['mesec_pocetka'] ?? 1)));
    $broj = $stavka ? program_planirani_broj_izvrsenja($stavka, (int)$godina, $planPocetak) : 0;
    return ['broj_termina'=>$broj, 'ukupno'=>$broj*(float)$cenaPoTerminu];
}

function predlozi_termine_programa($conn,$szId,$godina) {
    // Svako novo računanje zamenjuje samo stare NEPOTVRĐENE automatske predloge.
    // Potvrđeni i izvršeni termini ostaju netaknuti.
    $stmtObrisiPredloge = $conn->prepare("DELETE t FROM program_odrzavanja_termini t JOIN program_odrzavanja_stavke s ON s.id=t.stavka_id WHERE s.sz_id=? AND YEAR(t.datum)=? AND t.status='planirano' AND t.predlozen=1");
    if ($stmtObrisiPredloge) {
        $sid = (int)$szId; $gy = (int)$godina;
        $stmtObrisiPredloge->bind_param('ii', $sid, $gy);
        $stmtObrisiPredloge->execute();
    }

    $tz=new DateTimeZone('Europe/Belgrade');
    $sada=new DateTimeImmutable('now',$tz);
    $tekucaGodina=(int)$sada->format('Y');
    $tekuciMesec=(int)$sada->format('n');
    $godina=(int)$godina;
    if($godina<$tekucaGodina) return 0; // novi predlozi nikada ne idu u prošlu godinu

    // Posle brisanja starih nepotvrđenih predloga, u projekciji ostaju svi
    // potvrđeni i izvršeni termini. To je ista cash-flow osnova koju prikazuje kalendar.
    $summary=finansijski_plan_summary($conn,$szId,$godina,true);
    $projekcija=[];
    for($m=1;$m<=12;$m++){
        $projekcija[$m]=(float)($summary['monthly'][$m]['stanje'] ?? 0);
    }

    $stavke=program_stavke_sa_cenama($conn,$szId,$godina);
    $planPocetak=max(1,min(12,(int)($summary['plan']['mesec_pocetka'] ?? 1)));
    $brojPredloga=0;

    foreach($stavke as $s){
        $pocetakStavke=max(1,min(12,(int)($s['pocetni_mesec'] ?? 1)));
        $od=max($planPocetak,$pocetakStavke,max(1,min(12,(int)$s['najraniji_mesec'])));
        $do=max($od,min(12,(int)$s['krajnji_mesec']));
        $interval=max(1,min(12,(int)$s['ucestalost_meseci']));
        // Kod plaćanja po terminu datum rada određuje i novčani odliv.
        // Kod jednokratnog/rata cash-flow je već određen posebnim datumima plaćanja.
        $cena=(($s['nacin_placanja'] ?? 'po_terminu')==='po_terminu') ? (float)$s['planirani_iznos'] : 0.0;

        // Svaka perioda dobija svoj prozor. Mesečna stavka ima prozor od jednog meseca,
        // tromesečna 3 meseca, godišnja ceo dozvoljeni period (najviše jedan termin godišnje).
        for($prozorOd=$od; $prozorOd<=$do; $prozorOd+=$interval){
            $prozorDo=min($do,$prozorOd+$interval-1);
            $kandidatOd=$prozorOd;
            if($godina===$tekucaGodina) $kandidatOd=max($kandidatOd,$tekuciMesec);
            if($kandidatOd>$prozorDo) continue; // ceo prozor je već prošao

            $postojeci=db_one($conn,
                "SELECT id FROM program_odrzavanja_termini WHERE stavka_id=? AND YEAR(datum)=? AND MONTH(datum) BETWEEN ? AND ? AND status IN ('planirano','izvrseno') LIMIT 1",
                'iiii',[(int)$s['id'],$godina,$prozorOd,$prozorDo]
            );
            if($postojeci) continue;

            $izabran=$kandidatOd;
            $imaSredstava=($cena<=0);
            if($cena>0){
                for($m=$kandidatOd;$m<=$prozorDo;$m++){
                    $izabran=$m;
                    if(($projekcija[$m]??0)>=$cena){ $imaSredstava=true; break; }
                }
            }

            $datum=sprintf('%04d-%02d-15',$godina,$izabran);
            $nap=$imaSredstava
                ? 'Predlog prema prioritetu, periodici i projekciji finansijskog plana.'
                : 'UPOZORENJE: projekcija sredstava nije dovoljna u ovom periodu; termin je postavljen na kraj dozvoljenog prozora.';
            $stmt=$conn->prepare("INSERT INTO program_odrzavanja_termini(stavka_id,datum,status,predlozen,napomena) VALUES (?,?,'planirano',1,?)");
            $sid=(int)$s['id'];
            $stmt->bind_param('iss',$sid,$datum,$nap);
            $stmt->execute();

            if($cena>0){
                for($m=$izabran;$m<=12;$m++) $projekcija[$m]-=$cena;
            }
            $brojPredloga++;
        }
    }
    return $brojPredloga;
}


/**
 * Pomeranje jednog planiranog termina Programa održavanja u drugi mesec.
 * Mesečne stavke (interval 1) namerno nisu pomerljive drag-drop metodom.
 * Finansijska nedovoljnost NE blokira pomeranje; vraća se kroz novu cash-flow projekciju.
 */
function program_pomeri_termin($conn, $terminId, $stavkaId, $szId, $godina, $noviMesec) {
    ensure_program_odrzavanja_schema($conn);
    $terminId=(int)$terminId; $stavkaId=(int)$stavkaId; $szId=(int)$szId; $godina=(int)$godina; $noviMesec=(int)$noviMesec;
    if ($noviMesec < 1 || $noviMesec > 12) return ['ok'=>false,'poruka'=>'Neispravan mesec.'];

    $row=db_one($conn,
        "SELECT t.*, s.sz_id, s.naziv, s.ucestalost_meseci, s.pocetni_mesec, s.najraniji_mesec, s.krajnji_mesec, s.aktivna
         FROM program_odrzavanja_termini t
         JOIN program_odrzavanja_stavke s ON s.id=t.stavka_id
         WHERE t.id=? AND t.stavka_id=? AND s.sz_id=? LIMIT 1",
        'iii',[$terminId,$stavkaId,$szId]
    );
    if(!$row) return ['ok'=>false,'poruka'=>'Termin nije pronađen.'];
    if((int)$row['aktivna']!==1) return ['ok'=>false,'poruka'=>'Stavka programa nije aktivna.'];
    if($row['status']!=='planirano') return ['ok'=>false,'poruka'=>'Realizovan ili zatvoren termin nije moguće pomerati.'];
    if((int)($row['predlozen'] ?? 0)!==1) return ['ok'=>false,'poruka'=>'Termin je potvrđen i zaključan. Prvo ga otključaj, pa ga zatim možeš pomeriti.'];
    if((int)$row['ucestalost_meseci']===1) return ['ok'=>false,'poruka'=>'Mesečne stavke se ne mogu pomerati drag-drop metodom.'];
    if((int)date('Y',strtotime($row['datum']))!==$godina) return ['ok'=>false,'poruka'=>'Termin ne pripada izabranoj godini.'];

    $plan=get_or_create_finansijski_plan($conn,$szId,$godina);
    $planPocetak=max(1,min(12,(int)($plan['mesec_pocetka']??1)));
    $od=max($planPocetak,(int)($row['pocetni_mesec']??1),(int)($row['najraniji_mesec']??1));
    $do=max($od,min(12,(int)($row['krajnji_mesec']??12)));

    $tz=new DateTimeZone('Europe/Belgrade');
    $sada=new DateTimeImmutable('now',$tz);
    if((int)$sada->format('Y')===$godina) $od=max($od,(int)$sada->format('n'));
    if($noviMesec<$od || $noviMesec>$do) {
        return ['ok'=>false,'poruka'=>'Termin može biti pomeren samo u dozvoljeni period '.mesec_kratko($od).'–'.mesec_kratko($do).'.'];
    }

    $duplikat=db_one($conn,
        "SELECT id FROM program_odrzavanja_termini WHERE stavka_id=? AND id<>? AND YEAR(datum)=? AND MONTH(datum)=? AND status IN ('planirano','izvrseno') LIMIT 1",
        'iiii',[$stavkaId,$terminId,$godina,$noviMesec]
    );
    if($duplikat) return ['ok'=>false,'poruka'=>'Ova stavka već ima termin u izabranom mesecu.'];

    $stariDan=(int)date('j',strtotime($row['datum']));
    $maxDan=(int)date('t',strtotime(sprintf('%04d-%02d-01',$godina,$noviMesec)));
    $dan=max(1,min($stariDan,$maxDan));
    $noviDatum=sprintf('%04d-%02d-%02d',$godina,$noviMesec,$dan);

    $stmt=$conn->prepare("UPDATE program_odrzavanja_termini SET datum=?, napomena=CONCAT(COALESCE(napomena,''), CASE WHEN COALESCE(napomena,'')='' THEN '' ELSE '\n' END, 'Termin ručno pomeren u kalendaru.') WHERE id=? AND stavka_id=?");
    $stmt->bind_param('sii',$noviDatum,$terminId,$stavkaId);
    $stmt->execute();

    $summary=finansijski_plan_summary($conn,$szId,$godina,true);
    $mesec=$summary['monthly'][$noviMesec]??null;
    $negativni=[];
    foreach(($summary['monthly']??[]) as $m=>$r){
        if((float)($r['stanje']??0)<0) $negativni[]=(int)$m;
    }
    return [
        'ok'=>true,
        'poruka'=>'Termin je pomeren.',
        'datum'=>$noviDatum,
        'finansijski_moguce'=>$mesec ? ((float)$mesec['stanje']>=0) : true,
        'mesecni_priliv'=>$mesec ? (float)$mesec['ocekivani_priliv'] : 0,
        'mesecni_odliv'=>$mesec ? (float)$mesec['odliv'] : 0,
        'saldo'=>$mesec ? (float)$mesec['stanje'] : 0,
        'negativni_meseci'=>$negativni,
    ];
}
