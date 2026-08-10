<?php
$szId = get_int('sz_id');
$tip = trim($_GET['tip'] ?? '');
$godina = isset($_GET['godina']) ? (int)$_GET['godina'] : current_year();
$auto = isset($_GET['auto']) && (int)$_GET['auto'] === 1;

if ($szId <= 0) {
    http_response_code(400);
    die('Nije izabrana stambena zajednica.');
}

$z = db_one($conn, "SELECT * FROM stambene_zajednice WHERE id=?", 'i', [$szId]);
if (!$z) {
    http_response_code(404);
    die('Stambena zajednica nije pronađena.');
}

$naslov = 'Dokument';
$landscape = false;
if ($tip === 'finansijski_plan') $naslov = 'Finansijski plan ' . $godina;
elseif ($tip === 'program') { $naslov = 'Program održavanja ' . $godina; $landscape = true; }
elseif ($tip === 'oprema') $naslov = 'Elementi i oprema';
else { http_response_code(400); die('Nepoznat tip izvoza.'); }

function pdfh($v) { return e((string)$v); }
function pdf_money($v) { return number_format((float)$v, 2, ',', '.') . ' RSD'; }
function pdf_date($v) {
    if (!$v) return '—';
    $t = strtotime($v);
    return $t ? date('d.m.Y.', $t) : (string)$v;
}
function pdf_period($months) {
    $m=(int)$months;
    if ($m===1) return 'Mesečno';
    if ($m===2) return 'Na 2 meseca';
    if ($m===3) return 'Tromesečno';
    if ($m===6) return 'Polugodišnje';
    if ($m===12) return 'Godišnje';
    return 'Na '.$m.' meseci';
}
?>
<!doctype html>
<html lang="sr">
<head>
<meta charset="utf-8">
<title><?= pdfh($naslov) ?> - <?= pdfh($z['naziv'] ?? '') ?></title>
<style>
@page{size:A4 <?= $landscape ? 'landscape' : 'portrait' ?>;margin:13mm}
*{box-sizing:border-box} body{font-family:Arial,"DejaVu Sans",sans-serif;color:#172033;margin:0;font-size:11px;line-height:1.35;background:#fff}
.printbar{position:sticky;top:0;z-index:10;background:#172033;color:#fff;padding:10px 14px;display:flex;gap:8px;align-items:center;justify-content:space-between}
.printbar button,.printbar a{border:0;border-radius:6px;padding:8px 12px;font-weight:700;text-decoration:none;cursor:pointer}.printbar button{background:#fff;color:#172033}.printbar a{background:#475467;color:#fff}
.doc{max-width:100%;margin:0 auto}.head{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #172033;padding-bottom:10px;margin-bottom:14px}.head h1{font-size:20px;margin:0 0 3px}.head h2{font-size:13px;margin:0;font-weight:600}.meta{text-align:right;font-size:10px;color:#475467}.meta b{color:#172033}
h3{font-size:13px;margin:17px 0 7px;border-bottom:1px solid #d0d5dd;padding-bottom:4px}.muted{color:#667085}.warn{color:#b42318;font-weight:700}.ok{color:#067647;font-weight:700}
table{width:100%;border-collapse:collapse;margin:5px 0 12px;page-break-inside:auto}thead{display:table-header-group}tr{page-break-inside:avoid;page-break-after:auto}th,td{border:1px solid #d0d5dd;padding:5px 6px;vertical-align:top}th{background:#f2f4f7;text-align:left;font-size:10px}.num{text-align:right;white-space:nowrap}.center{text-align:center}.small{font-size:9px}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:7px;margin:8px 0 13px}.summary>div{border:1px solid #d0d5dd;padding:8px;border-radius:5px}.summary span{display:block;color:#667085;font-size:9px}.summary strong{display:block;margin-top:2px;font-size:12px}.page-break{break-before:page;page-break-before:always}.note{border-left:3px solid #98a2b3;padding:6px 8px;background:#f9fafb}.footer-note{margin-top:18px;padding-top:7px;border-top:1px solid #d0d5dd;font-size:9px;color:#667085}
@media print{.printbar{display:none!important}body{font-size:10px}.doc{margin:0}.no-print{display:none!important}a{color:inherit;text-decoration:none}}
</style>
</head>
<body>
<div class="printbar no-print"><div><b><?= pdfh($naslov) ?></b> · pregled za PDF/štampu</div><div><a href="javascript:window.close()">Zatvori</a> <button onclick="window.print()">Sačuvaj kao PDF / Štampaj</button></div></div>
<div class="doc">
    <div class="head">
        <div>
            <h1><?= pdfh($naslov) ?></h1>
            <h2><?= pdfh($z['naziv'] ?? '') ?></h2>
            <div class="muted"><?= pdfh(trim(($z['adresa'] ?? '').(($z['grad'] ?? '') ? ', '.$z['grad'] : ''))) ?></div>
        </div>
        <div class="meta">
            <?php if (!empty($z['maticni_broj'])): ?><div>Matični broj: <b><?= pdfh($z['maticni_broj']) ?></b></div><?php endif; ?>
            <?php if (!empty($z['pib'])): ?><div>PIB: <b><?= pdfh($z['pib']) ?></b></div><?php endif; ?>
            <?php if (!empty($z['tekuci_racun'])): ?><div>Račun: <b><?= pdfh($z['tekuci_racun']) ?></b></div><?php endif; ?>
            <div>Generisano: <b><?= date('d.m.Y. H:i') ?></b></div>
        </div>
    </div>

<?php if ($tip === 'oprema'):
    $elementi = db_all($conn,
        "SELECT oz.*, se.naziv AS element_naziv, se.kategorija, se.koristi_kolicinu
         FROM oprema_zgrade oz JOIN sifarnik_elemenata se ON se.id=oz.element_id
         WHERE oz.sz_id=? AND oz.aktivna=1 ORDER BY se.kategorija,se.naziv", 'i', [$szId]);
    $grupe=[]; foreach($elementi as $el) $grupe[$el['kategorija'] ?: 'Ostalo'][]=$el;
?>
    <div class="summary">
        <div><span>Broj evidentiranih stavki</span><strong><?= count($elementi) ?></strong></div>
        <div><span>Broj kategorija</span><strong><?= count($grupe) ?></strong></div>
        <div><span>Posebni delovi</span><strong><?= (int)($z['broj_posebnih_delova'] ?? 0) ?></strong></div>
        <div><span>Garažna mesta</span><strong><?= (int)($z['broj_garaznih_mesta'] ?? 0) ?></strong></div>
    </div>
    <?php if (!$elementi): ?><p class="muted">Nema evidentirane opreme i elemenata.</p><?php endif; ?>
    <?php foreach($grupe as $kat=>$rows): ?>
        <h3><?= pdfh($kat) ?></h3>
        <table><thead><tr><th>Element / oprema</th><th class="center" style="width:90px">Količina</th><th>Napomena / opis</th></tr></thead><tbody>
        <?php foreach($rows as $el): ?><tr><td><?= pdfh($el['element_naziv']) ?></td><td class="center"><?= ((int)($el['koristi_kolicinu'] ?? 1)===1) ? pdfh($el['kolicina'] ?? 1) : '—' ?></td><td><?= nl2br(pdfh($el['napomena'] ?? '')) ?></td></tr><?php endforeach; ?>
        </tbody></table>
    <?php endforeach; ?>

<?php elseif ($tip === 'finansijski_plan'):
    $summary = finansijski_plan_summary($conn,$szId,$godina,true);
    $plan = $summary['plan'];
?>
    <div class="summary">
        <div><span>Početno stanje</span><strong><?= pdf_money($summary['pocetnoStanje']) ?></strong></div>
        <div><span>Očekivani priliv</span><strong><?= pdf_money($summary['ocekivaniPriliv']) ?></strong></div>
        <div><span>Planirani odlivi</span><strong><?= pdf_money($summary['ukupniOdlivi']) ?></strong></div>
        <div><span>Očekivano stanje 31.12.</span><strong><?= pdf_money($summary['ocekivanoKrajGodine']) ?></strong></div>
    </div>
    <p class="note"><b>Početak finansijskog plana:</b> <?= pdfh(mesec_naziv((int)($plan['mesec_pocetka'] ?? 1))) ?> <?= (int)$godina ?>. &nbsp; <b>Planirani stepen naplate:</b> <?= pdfh($plan['stepen_naplate'] ?? 100) ?>%.</p>

    <h3>Mesečna projekcija</h3>
    <table><thead><tr><th>Mesec</th><th class="num">Očekivani priliv</th><th class="num">Odliv</th><th class="num">Mesečni saldo</th><th class="num">Stanje računa</th></tr></thead><tbody>
    <?php foreach($summary['monthly'] as $m): ?><tr><td><?= pdfh($m['naziv']) ?></td><td class="num"><?= pdf_money($m['ocekivani_priliv']) ?></td><td class="num"><?= pdf_money($m['odliv']) ?></td><td class="num"><?= pdf_money($m['saldo']) ?></td><td class="num <?= $m['stanje']<0?'warn':'' ?>"><?= pdf_money($m['stanje']) ?></td></tr><?php endforeach; ?>
    </tbody></table>

    <h3>Planirani prilivi</h3>
    <table><thead><tr><th>Stavka</th><th>Osnov</th><th>Period</th><th class="num">Planirano</th></tr></thead><tbody>
    <?php foreach($summary['basePrilivi'] as $r): ?><tr><td><?= pdfh($r['label'] ?? $r['naziv'] ?? '') ?></td><td><?= pdfh($r['detail'] ?? '') ?></td><td>Mesečno</td><td class="num"><?= pdf_money($r['total'] ?? 0) ?></td></tr><?php endforeach; ?>
    <?php foreach($summary['stavke'] as $s): if(($s['tip']??'')!=='priliv') continue; $tot=0; for($m=$summary['planPocetak'];$m<=12;$m++) $tot+=stavka_month_value($s,$summary['metrics'],$m); ?><tr><td><?= pdfh($s['naziv']) ?></td><td><?= pdfh($s['osnov'] ?? '') ?></td><td><?= pdfh($s['period'] ?? '') ?></td><td class="num"><?= pdf_money($tot) ?></td></tr><?php endforeach; ?>
    </tbody></table>

    <h3>Planirani odlivi</h3>
    <table><thead><tr><th>Stavka</th><th>Izvor / napomena</th><th class="num">Planirano</th></tr></thead><tbody>
    <?php foreach($summary['stavke'] as $s): if(($s['tip']??'')!=='odliv') continue; $tot=0; for($m=$summary['planPocetak'];$m<=12;$m++) $tot+=stavka_month_value($s,$summary['metrics'],$m); ?><tr><td><?= pdfh($s['naziv']) ?></td><td><?= pdfh($s['grupa'] ?? '') ?></td><td class="num"><?= pdf_money($tot) ?></td></tr><?php endforeach; ?>
    <?php $prog=program_stavke_sa_cenama($conn,$szId,$godina); foreach($prog as $s):
        $ima=program_stavka_ima_cenu($s); $cena=(float)($s['planirani_iznos']??0); $nacin=$s['nacin_placanja']??'po_terminu';
        if(!$ima){$tot=null;$izv='Cena nije definisana - izaberite ponudu/cenovnik ili unesite procenu.';}
        elseif($nacin==='po_terminu'){ $obr=program_godisnji_iznos_stavke($conn,(int)$s['id'],$godina,$cena); $tot=$obr['ukupno']; $izv=$s['izvor_cene_naziv']??''; }
        else { $rate=program_placanja_stavke($conn,(int)$s['id'],$godina); $tot=0; foreach($rate as $r)$tot+=(float)$r['iznos']; $izv=($s['izvor_cene_naziv']??'').' · '.($nacin==='rate'?'plaćanje na rate':'jednokratno plaćanje'); }
    ?><tr><td><?= pdfh($s['naziv']) ?></td><td class="<?= !$ima?'warn':'' ?>"><?= pdfh($izv) ?></td><td class="num"><?= $tot===null?'—':pdf_money($tot) ?></td></tr><?php endforeach; ?>
    </tbody></table>

<?php elseif ($tip === 'program'):
    $stavke=program_stavke_sa_cenama($conn,$szId,$godina);
    $fins=finansijski_plan_summary($conn,$szId,$godina,true);
?>
    <div class="summary">
        <div><span>Broj stavki programa</span><strong><?= count($stavke) ?></strong></div>
        <div><span>Očekivani priliv</span><strong><?= pdf_money($fins['ocekivaniPriliv']) ?></strong></div>
        <div><span>Programski odlivi</span><strong><?= pdf_money($fins['programUkupno']) ?></strong></div>
        <div><span>Stanje 31.12.</span><strong><?= pdf_money($fins['ocekivanoKrajGodine']) ?></strong></div>
    </div>

    <h3>Program održavanja</h3>
    <table><thead><tr><th>Aktivnost</th><th>Kategorija</th><th>Prioritet</th><th>Periodika</th><th>Termin(i) radova</th><th>Izvor cene / izvođač</th><th class="num">Cena</th><th>Plan plaćanja</th></tr></thead><tbody>
    <?php foreach($stavke as $s):
        $termini=program_termini_stavke($conn,(int)$s['id'],$godina);
        $ima=program_stavka_ima_cenu($s);
        $plac=program_plan_placanja_status($conn,$s,$godina);
        $rate=program_placanja_stavke($conn,(int)$s['id'],$godina);
        $termTxt=[]; foreach($termini as $t)$termTxt[]=pdf_date($t['datum']).' ('.($t['status']==='izvrseno'?'izvršeno':'planirano').')';
        $payTxt=[];
        if(!$ima)$payTxt[]='Cena nije definisana';
        elseif(($s['nacin_placanja']??'po_terminu')==='po_terminu')$payTxt[]='Plaćanje u terminu aktivnosti';
        else foreach($rate as $i=>$r)$payTxt[]=(count($rate)>1?'Rata '.($i+1).'/'.count($rate).': ':'').pdf_date($r['datum_placanja']).' - '.pdf_money($r['iznos']);
    ?>
    <tr>
        <td><b><?= pdfh($s['naziv']) ?></b><?php if(!empty($s['opis'])):?><div class="small muted"><?= nl2br(pdfh($s['opis'])) ?></div><?php endif;?></td>
        <td><?= pdfh($s['kategorija'] ?? '') ?></td>
        <td><?= pdfh(ucfirst($s['prioritet'] ?? 'srednje')) ?></td>
        <td><?= pdfh(pdf_period($s['ucestalost_meseci'] ?? 12)) ?><div class="small muted">od <?= pdfh(mesec_naziv((int)($s['pocetni_mesec'] ?? 1))) ?></div></td>
        <td><?= $termTxt?implode('<br>',array_map('pdfh',$termTxt)):'<span class="warn">Termin nije definisan</span>' ?></td>
        <td class="<?= !$ima?'warn':'' ?>"><?= $ima?pdfh($s['izvor_cene_naziv']??''):'Cena nije definisana' ?><?php if(!empty($s['izvodjac_naziv'])):?><div class="small muted"><?= pdfh($s['izvodjac_naziv']) ?></div><?php endif;?></td>
        <td class="num"><?= $ima?pdf_money($s['planirani_iznos']):'—' ?></td>
        <td class="small"><?= implode('<br>',array_map('pdfh',$payTxt)) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>

    <h3>Mesečni finansijski balans</h3>
    <table><thead><tr><th>Mesec</th><th class="num">Prilivi</th><th class="num">Odlivi</th><th class="num">Saldo meseca</th><th class="num">Stanje računa</th></tr></thead><tbody>
    <?php foreach($fins['monthly'] as $m): ?><tr><td><?= pdfh($m['naziv']) ?></td><td class="num"><?= pdf_money($m['ocekivani_priliv']) ?></td><td class="num"><?= pdf_money($m['odliv']) ?></td><td class="num"><?= pdf_money($m['saldo']) ?></td><td class="num <?= $m['stanje']<0?'warn':'' ?>"><?= pdf_money($m['stanje']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
<?php endif; ?>

<div class="footer-note">Dokument je generisan iz JP Property Manager sistema. Za PDF izaberite „Sačuvaj kao PDF“ u dijalogu za štampu.</div>
</div>
<?php if ($auto): ?><script>window.addEventListener('load',()=>setTimeout(()=>window.print(),250));</script><?php endif; ?>
</body>
</html>
