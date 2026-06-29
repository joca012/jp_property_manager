<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

$allowed = [
    'dashboard',
    'zajednice',
    'zajednica',
    'dodaj_zajednicu',
    'finansijski_plan',
    'finansijski_plan_stavka',
    'finansijski_plan_obrisi',
    'finansijski_plan_rebalans',
    'budzet',
    'oprema',
    'elementi_zgrade',
    'elementi_zgrade_pregled',
    'program',
    'kvarovi',
    'dokumentacija',
    'izvestaji',
    'ponude',
    'izvodjaci'
];

$page = $_GET['page'] ?? 'dashboard';
if (!in_array($page, $allowed, true)) {
    $page = 'dashboard';
}

$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($pageFile)) {
    $pageFile = __DIR__ . '/pages/dashboard.php';
}
require $pageFile;
