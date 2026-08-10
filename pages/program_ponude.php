<?php
ensure_program_odrzavanja_schema($conn);
$szId=get_int('sz_id');
$godina=get_int('godina',current_year());
$pid=get_int('program_id');
$s=db_one($conn,"SELECT * FROM program_odrzavanja_stavke WHERE id=? AND sz_id=?",'ii',[$pid,$szId]);
if(!$s) die('Stavka programa nije pronađena.');
$title='Cena i plaćanje';
$subtitle=$s['naziv'];
$greska='';

function obrisi_program_placanja($conn,$pid){
    $del=$conn->prepare("DELETE FROM program_odrzavanja_placanja WHERE program_id=?");
    $del->bind_param('i',$pid); $del->execute();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';

    if($action==='izaberi_ponudu'){
        $ponudaId=(int)post_value('ponuda_id');
        $p=$ponudaId?db_one($conn,"SELECT * FROM ponude WHERE id=? AND aktivna=1 AND (sz_id IS NULL OR sz_id=0 OR sz_id=?)",'ii',[$ponudaId,$szId]):null;
        if($p){
            obrisi_program_placanja($conn,$pid);
            $conn->query("UPDATE program_odrzavanje_ponude SET izabrana=0 WHERE program_id=".(int)$pid);
            $st=$conn->prepare("INSERT INTO program_odrzavanje_ponude(program_id,ponuda_id,izabrana) VALUES(?,?,1) ON DUPLICATE KEY UPDATE izabrana=1");
            $st->bind_param('ii',$pid,$ponudaId);$st->execute();
            $iz=(int)($p['izvodjac_id']??0);
            $defaultNacin='po_terminu';
            $st=$conn->prepare("UPDATE program_odrzavanja_stavke SET izvor_cene='ponuda',ponuda_id=?,cenovnik_stavka_id=NULL,jedinicna_cena=NULL,kolicina=1,ukupna_cena=NULL,izvodjac_id=?,nacin_placanja=? WHERE id=? AND sz_id=?");
            $st->bind_param('iisii',$ponudaId,$iz,$defaultNacin,$pid,$szId);$st->execute();
            $conn->query("UPDATE ponude SET status_ponude='prihvacena', program_stavka_id=".(int)$pid." WHERE id=".(int)$ponudaId);
        }
        redirect_to("index.php?page=program_ponude&program_id=$pid&sz_id=$szId&godina=$godina");
    }

    if($action==='izaberi_cenovnik'){
        $csid=(int)($_POST['cenovnik_stavka_id']??0);
        $kol=max(0.001,(float)str_replace(',','.',(string)($_POST['kolicina']??'1')));
        $izcol=first_existing_column($conn,'izvodjaci',['naziv','ime','naziv_firme','firma'],'naziv');
        $cs=$csid?db_one($conn,"SELECT cs.*,c.izvodjac_id,c.aktivan cenovnik_aktivan,oj.oznaka jedinica_oznaka FROM cenovnik_stavke cs JOIN cenovnici c ON c.id=cs.cenovnik_id JOIN obracunske_jedinice oj ON oj.id=cs.jedinica_id WHERE cs.id=? AND cs.aktivna=1 AND c.aktivan=1",'i',[$csid]):null;
        if($cs){
            $kolOprema=program_kolicina_iz_opreme($conn,$szId,(int)$cs['element_id'],(string)($cs['jedinica_oznaka']??''));
            if($kolOprema>0) $kol=$kolOprema; // PP aparati/hidranti/liftovi: količina iz evidencije opreme
            obrisi_program_placanja($conn,$pid);
            $jed=(float)$cs['cena']; $uk=round($jed*$kol,2); $iz=(int)$cs['izvodjac_id'];
            $defaultNacin='po_terminu';
            $st=$conn->prepare("UPDATE program_odrzavanja_stavke SET izvor_cene='cenovnik',ponuda_id=NULL,cenovnik_stavka_id=?,jedinicna_cena=?,kolicina=?,ukupna_cena=?,izvodjac_id=?,nacin_placanja=? WHERE id=? AND sz_id=?");
            $st->bind_param('idddisii',$csid,$jed,$kol,$uk,$iz,$defaultNacin,$pid,$szId);$st->execute();
        }
        redirect_to("index.php?page=program_ponude&program_id=$pid&sz_id=$szId&godina=$godina");
    }

    if($action==='sacuvaj_procenu'){
        $uk=max(0.0,(float)str_replace(',','.',(string)($_POST['ukupna_cena']??'0')));
        if($uk<=0) $greska='Unesi procenjenu vrednost veću od nule.';
        else {
            obrisi_program_placanja($conn,$pid);
            $defaultNacin='po_terminu';
            $st=$conn->prepare("UPDATE program_odrzavanja_stavke SET izvor_cene='procena',ponuda_id=NULL,cenovnik_stavka_id=NULL,jedinicna_cena=?,kolicina=1,ukupna_cena=?,izvodjac_id=NULL,nacin_placanja=? WHERE id=? AND sz_id=?");
            $st->bind_param('ddsii',$uk,$uk,$defaultNacin,$pid,$szId);$st->execute();
            redirect_to("index.php?page=program_ponude&program_id=$pid&sz_id=$szId&godina=$godina");
        }
    }

    if($action==='ukloni_cenu'){
        obrisi_program_placanja($conn,$pid);
        $st=$conn->prepare("UPDATE program_odrzavanja_stavke SET izvor_cene=NULL,ponuda_id=NULL,cenovnik_stavka_id=NULL,jedinicna_cena=NULL,kolicina=1,ukupna_cena=NULL,izvodjac_id=NULL,nacin_placanja='po_terminu' WHERE id=? AND sz_id=?");
        $st->bind_param('ii',$pid,$szId);$st->execute();
        redirect_to("index.php?page=program_ponude&program_id=$pid&sz_id=$szId&godina=$godina");
    }

    if($action==='sacuvaj_nacin'){
        $nacin=$_POST['nacin_placanja']??'po_terminu';
        if(!in_array($nacin,['po_terminu','jednokratno','rate'],true)) $nacin='po_terminu';
        $stari=$s['nacin_placanja']??'po_terminu';
        if($nacin!==$stari) obrisi_program_placanja($conn,$pid);
        $st=$conn->prepare("UPDATE program_odrzavanja_stavke SET nacin_placanja=? WHERE id=? AND sz_id=?");
        $st->bind_param('sii',$nacin,$pid,$szId);$st->execute();
        redirect_to("index.php?page=program_ponude&program_id=$pid&sz_id=$szId&godina=$godina");
    }

    $s=db_one($conn,"SELECT * FROM program_odrzavanja_stavke WHERE id=? AND sz_id=?",'ii',[$pid,$szId]);
    $s['planirani_iznos']=program_stavka_cena($conn,$s);
    $ukupno=(float)$s['planirani_iznos'];

    if($action==='sacuvaj_jednokratno' && $ukupno>0){
        $datum=trim((string)($_POST['datum_placanja']??''));
        if(!$datum || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$datum)) $greska='Unesi ispravan datum plaćanja.';
        else {
            $conn->begin_transaction();
            try{
                obrisi_program_placanja($conn,$pid);
                $proc=100.0; $nap='Jednokratno plaćanje'; $pon=!empty($s['ponuda_id'])?(int)$s['ponuda_id']:null;
                $st=$conn->prepare("INSERT INTO program_odrzavanja_placanja(program_id,ponuda_id,datum_placanja,iznos,procenat,napomena) VALUES(?,?,?,?,?,?)");
                $st->bind_param('iisdds',$pid,$pon,$datum,$ukupno,$proc,$nap);$st->execute();
                $conn->commit();
                redirect_to("index.php?page=program_ponude&program_id=$pid&sz_id=$szId&godina=$godina");
            }catch(Throwable $e){$conn->rollback();$greska='Plaćanje nije sačuvano.';}
        }
    }

    if($action==='dodaj_ratu' && $ukupno>0){
        $datum=trim((string)($_POST['datum_placanja']??''));
        $iznos=(float)str_replace(',','.',(string)($_POST['iznos']??'0'));
        $procenat=(float)str_replace(',','.',(string)($_POST['procenat']??'0'));
        $napomena=trim((string)($_POST['napomena']??''));
        if(!$datum || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$datum)) $greska='Unesi ispravan datum rate.';
        elseif($iznos<=0 && $procenat<=0) $greska='Unesi iznos ili procenat rate.';
        else {
            if($iznos<=0 && $procenat>0) $iznos=round($ukupno*$procenat/100,2);
            if($iznos>0) $procenat=$ukupno>0?round($iznos/$ukupno*100,3):0;
            $postojeci=program_placanja_stavke($conn,$pid,null);$zbir=0.0;foreach($postojeci as $r)$zbir+=(float)$r['iznos'];
            if($zbir+$iznos>$ukupno+0.01) $greska='Zbir rata ne može biti veći od ukupne vrednosti.';
            else {
                $pon=!empty($s['ponuda_id'])?(int)$s['ponuda_id']:null;
                $st=$conn->prepare("INSERT INTO program_odrzavanja_placanja(program_id,ponuda_id,datum_placanja,iznos,procenat,napomena) VALUES(?,?,?,?,?,?)");
                $st->bind_param('iisdds',$pid,$pon,$datum,$iznos,$procenat,$napomena);$st->execute();
                redirect_to("index.php?page=program_ponude&program_id=$pid&sz_id=$szId&godina=$godina");
            }
        }
    }

    if($action==='obrisi_ratu'){
        $rid=(int)($_POST['rata_id']??0);
        if($rid){$st=$conn->prepare("DELETE FROM program_odrzavanja_placanja WHERE id=? AND program_id=?");$st->bind_param('ii',$rid,$pid);$st->execute();}
        redirect_to("index.php?page=program_ponude&program_id=$pid&sz_id=$szId&godina=$godina");
    }
}

$s=db_one($conn,"SELECT * FROM program_odrzavanja_stavke WHERE id=? AND sz_id=?",'ii',[$pid,$szId]);
if(empty($s['izvor_cene']) && !empty($s['ponuda_id'])) $s['izvor_cene']='ponuda';
$s['planirani_iznos']=program_stavka_cena($conn,$s);
$statusPlacanja=program_plan_placanja_status($conn,$s,null);
$izcol=first_existing_column($conn,'izvodjaci',['naziv','ime','naziv_firme','firma'],'naziv');
$ponude=db_all($conn,"SELECT p.*,i.`$izcol` izvodjac_naziv,COALESCE(NULLIF(p.iznos,0),(SELECT SUM(ps.kolicina*ps.cena) FROM ponuda_stavke ps WHERE ps.ponuda_id=p.id AND ps.aktivna=1),0) ukupno, (SELECT GROUP_CONCAT(CONCAT_WS(' ',ps.naziv,se.naziv,sa.naziv) SEPARATOR ' | ') FROM ponuda_stavke ps LEFT JOIN sifarnik_elemenata se ON se.id=ps.element_id LEFT JOIN sifarnik_aktivnosti sa ON sa.id=ps.aktivnost_id WHERE ps.ponuda_id=p.id AND ps.aktivna=1) stavke_tekst FROM ponude p LEFT JOIN izvodjaci i ON i.id=p.izvodjac_id WHERE p.aktivna=1 AND (p.sz_id IS NULL OR p.sz_id=0 OR p.sz_id=?) ORDER BY p.datum_ponude DESC,p.id DESC",'i',[$szId]);
$ponude=array_values(array_filter($ponude,function($p) use ($s,$pid){
    if((int)($p['program_stavka_id']??0)===(int)$pid) return true;
    $txt=trim(($p['naziv']??'').' '.($p['opis']??'').' '.($p['kategorija']??'').' '.($p['stavke_tekst']??''));
    return program_price_candidate_matches($s,$txt);
}));

$cenovnikStavke=db_all($conn,"SELECT cs.id,cs.element_id,cs.jedinica_id,cs.cena,cs.napomena,c.id cenovnik_id,c.naziv cenovnik_naziv,c.datum_od,c.datum_do,i.`$izcol` izvodjac_naziv,se.naziv element_naziv,se.kategorija element_kategorija,sa.naziv aktivnost_naziv,oj.naziv jedinica_naziv,oj.oznaka jedinica_oznaka FROM cenovnik_stavke cs JOIN cenovnici c ON c.id=cs.cenovnik_id LEFT JOIN izvodjaci i ON i.id=c.izvodjac_id JOIN sifarnik_elemenata se ON se.id=cs.element_id JOIN sifarnik_aktivnosti sa ON sa.id=cs.aktivnost_id JOIN obracunske_jedinice oj ON oj.id=cs.jedinica_id WHERE cs.aktivna=1 AND c.aktivan=1 ORDER BY c.naziv, se.naziv, sa.naziv",'',[]);
$cenovnikStavke=array_values(array_filter($cenovnikStavke,function($cs) use ($s){
    return program_price_candidate_matches($s,trim(($cs['napomena']??'').' '.($cs['element_kategorija']??'')),$cs['element_naziv']??'',$cs['aktivnost_naziv']??'');
}));
foreach($cenovnikStavke as &$cs){ $cs['oprema_kolicina']=program_kolicina_iz_opreme($conn,$szId,(int)$cs['element_id'],(string)($cs['jedinica_oznaka']??'')); } unset($cs);
require __DIR__.'/../includes/header.php';
?>
<style>
.payment-status{padding:10px 12px;border-radius:10px;margin:12px 0}.payment-status.ok{background:#ecfdf3;color:#067647}.payment-status.warn{background:#fff7ed;color:#9a3412}.payment-status.bad{background:#fff1f2;color:#b42318}.payment-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:end}.source-tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.source-card{border:1px solid var(--line);border-radius:12px;padding:14px}.source-card.active{border:2px solid #2563eb;background:#eff6ff}.price-source-badge{display:inline-block;border-radius:999px;padding:4px 8px;background:#eef2ff;color:#3730a3;font-size:11px;font-weight:800}@media(max-width:900px){.payment-grid,.source-tabs{grid-template-columns:1fr 1fr}}@media(max-width:600px){.payment-grid,.source-tabs{grid-template-columns:1fr}}
</style>
<section class="card"><div class="toolbar"><div><h2><?= e($s['naziv']) ?></h2><span class="price-source-badge"><?= e(program_izvor_cene_label($s)) ?></span></div><div class="actions"><a class="btn btn-primary" href="index.php?page=ponude&sz_id=<?= $szId ?>">+ Nova ponuda</a><a class="btn btn-light" href="index.php?page=program&sz_id=<?= $szId ?>&godina=<?= $godina ?>">← Program</a></div></div><p class="muted">Izvor cene, termin radova i termini plaćanja su odvojeni podaci. Cena iz cenovnika se snima kao snapshot, pa kasnija promena cenovnika ne menja ovaj program.</p></section>
<?php if($greska):?><section class="card" style="margin-top:12px;border-left:4px solid #b42318"><?= e($greska) ?></section><?php endif;?>

<section class="card" style="margin-top:18px">
<div class="toolbar"><h2>Izvor cene</h2><?php if($s['planirani_iznos']>0): ?><form method="post" onsubmit="return confirm('Ukloniti definisanu cenu i plan plaćanja?')"><input type="hidden" name="action" value="ukloni_cenu"><button class="btn btn-danger btn-sm">Ukloni cenu</button></form><?php endif; ?></div>
<div class="source-tabs">
<div class="source-card <?= ($s['izvor_cene']??'')==='ponuda'?'active':'' ?>"><strong>1. Ponuda</strong><p class="muted">Za pojedinačne radove i ugovorene poslove.</p></div>
<div class="source-card <?= ($s['izvor_cene']??'')==='cenovnik'?'active':'' ?>"><strong>2. Cenovnik</strong><p class="muted">Za aktivnosti sa već ugovorenom jediničnom cenom.</p></div>
<div class="source-card <?= ($s['izvor_cene']??'')==='procena'?'active':'' ?>"><strong>3. Procena</strong><p class="muted">Privremeni planski iznos dok nema ponude/cenovnika.</p></div>
</div>
</section>

<section class="card" style="margin-top:18px">
<div class="toolbar"><div><h2>Dostupni cenovnici i ponude</h2><p class="muted" style="margin:4px 0 0">Prikazuju se samo izbori koji odgovaraju ovoj aktivnosti: <strong><?= e($s['naziv']) ?></strong>.</p></div></div>
<?php if(empty($ponude) && empty($cenovnikStavke)): ?>
<div class="payment-status warn"><strong>Nema odgovarajućih ponuda ili stavki cenovnika.</strong> Možeš dodati novu ponudu ili koristiti procenu ispod.</div>
<?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Izvor</th><th>Dostupna cena</th><th>Izvođač</th><th>Obračun</th><th>Vrednost</th><th></th></tr></thead><tbody>
<?php foreach($ponude as $p): ?>
<tr>
<td><span class="price-source-badge">PONUDA</span></td>
<td><strong><?= e($p['naziv']) ?></strong><div class="muted"><?= e($p['broj_ponude']??'') ?></div></td>
<td><?= e($p['izvodjac_naziv']??$p['dobavljac']??'') ?></td>
<td><span class="muted">ukupna ponuđena cena</span></td>
<td><strong><?= money_rs($p['ukupno']) ?></strong></td>
<td><form method="post" onsubmit="return confirm('Izbor druge cene briše postojeći ručno definisan plan plaćanja. Nastaviti?')"><input type="hidden" name="action" value="izaberi_ponudu"><input type="hidden" name="ponuda_id" value="<?= $p['id'] ?>"><button class="btn btn-primary btn-sm" <?= (($s['izvor_cene']??'')==='ponuda' && (int)$s['ponuda_id']===(int)$p['id'])?'disabled':'' ?>><?= (($s['izvor_cene']??'')==='ponuda' && (int)$s['ponuda_id']===(int)$p['id'])?'Izabrana':'Izaberi' ?></button></form></td>
</tr>
<?php endforeach; ?>
<?php foreach($cenovnikStavke as $cs): $autoKol=(float)($cs['oprema_kolicina']??0); $trenKol=(($s['izvor_cene']??'')==='cenovnik' && (int)$s['cenovnik_stavka_id']===(int)$cs['id'])?(float)$s['kolicina']:1; ?>
<tr>
<td><span class="price-source-badge">CENOVNIK</span></td>
<td><strong><?= e($cs['aktivnost_naziv']) ?> — <?= e($cs['element_naziv']) ?></strong><div class="muted"><?= e($cs['cenovnik_naziv']) ?></div></td>
<td><?= e($cs['izvodjac_naziv']??'') ?></td>
<td><form method="post" class="actions" onsubmit="return confirm('Izbor cene iz cenovnika briše postojeći ručno definisan plan plaćanja. Nastaviti?')"><input type="hidden" name="action" value="izaberi_cenovnik"><input type="hidden" name="cenovnik_stavka_id" value="<?= (int)$cs['id'] ?>"><?php if($autoKol>0): ?><input type="hidden" name="kolicina" value="<?= e($autoKol) ?>"><strong><?= e(rtrim(rtrim(number_format($autoKol,3,'.',''),'0'),'.')) ?> × <?= e($cs['jedinica_oznaka']??$cs['jedinica_naziv']) ?></strong><div class="muted">količina automatski iz opreme</div><?php else: ?><label class="muted">Količina <input type="number" name="kolicina" min="0.001" step="0.001" value="<?= e($trenKol) ?>" style="width:85px"></label><?php endif; ?></td>
<td><strong><?= money_rs($cs['cena']) ?></strong><div class="muted">po <?= e($cs['jedinica_oznaka']??$cs['jedinica_naziv']) ?><?php if($autoKol>0): ?> · ukupno <?= money_rs((float)$cs['cena']*$autoKol) ?><?php endif; ?></div></td>
<td><button class="btn btn-primary btn-sm"><?= (($s['izvor_cene']??'')==='cenovnik' && (int)$s['cenovnik_stavka_id']===(int)$cs['id'])?'Ponovo obračunaj':'Izaberi' ?></button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>

<section class="card" style="margin-top:18px"><h2>Procena / ručni planski iznos</h2><form method="post" class="actions" onsubmit="return confirm('Procena će postati aktivan izvor cene i obrisaće postojeći ručno definisan plan plaćanja. Nastaviti?')"><input type="hidden" name="action" value="sacuvaj_procenu"><label>Ukupna procenjena vrednost RSD <input type="number" step="0.01" min="0.01" name="ukupna_cena" value="<?= ($s['izvor_cene']??'')==='procena'?e($s['ukupna_cena']):'' ?>" required></label><button class="btn btn-light">Koristi procenu</button></form></section>

<?php if($s['planirani_iznos']>0): ?>
<section class="card" style="margin-top:18px">
<div class="toolbar"><div><h2>Plan plaćanja</h2><div class="muted">Ukupna vrednost: <strong><?= money_rs($s['planirani_iznos']) ?></strong> · izvor: <?= e(program_izvor_cene_label($s)) ?><?php if(($s['izvor_cene']??'')==='cenovnik'): ?> · <?= e($s['kolicina']) ?> × <?= money_rs($s['jedinicna_cena']) ?><?php endif; ?></div></div></div>
<div class="payment-status ok"><strong>Podrazumevano:</strong> plaćanje je u istom terminu kao aktivnost i ne zahteva dodatno podešavanje.</div>
<details <?= (($s['nacin_placanja']??'po_terminu')!=='po_terminu')?'open':'' ?>><summary style="cursor:pointer;font-weight:700">Promeni način plaćanja (opciono)</summary><form method="post" class="actions" style="margin-top:10px" onsubmit="return confirm('Promena načina plaćanja briše postojeći ručno definisan raspored plaćanja. Nastaviti?')"><input type="hidden" name="action" value="sacuvaj_nacin"><select name="nacin_placanja"><option value="po_terminu" <?= ($s['nacin_placanja']??'po_terminu')==='po_terminu'?'selected':'' ?>>Podrazumevano — u terminu aktivnosti</option><option value="jednokratno" <?= ($s['nacin_placanja']??'')==='jednokratno'?'selected':'' ?>>Plaćanje drugog datuma</option><option value="rate" <?= ($s['nacin_placanja']??'')==='rate'?'selected':'' ?>>Na rate</option></select><button class="btn btn-light btn-sm">Sačuvaj</button></form></details>

<?php if(($s['nacin_placanja']??'po_terminu')==='po_terminu'): ?>
<div class="muted" style="margin-top:10px">Odliv će automatski pratiti termin aktivnosti.</div>
<?php elseif(($s['nacin_placanja']??'')==='jednokratno'): ?>
<div class="payment-status <?= $statusPlacanja['kompletno']?'ok':'warn' ?>"><strong><?= $statusPlacanja['kompletno']?'Plaćanje je definisano.':'Potrebno je definisati datum jednokratnog plaćanja.' ?></strong></div>
<form method="post" class="payment-grid"><input type="hidden" name="action" value="sacuvaj_jednokratno"><label>Datum plaćanja<input type="date" name="datum_placanja" required value="<?= e(($statusPlacanja['sve_rate'][0]['datum_placanja']??'')) ?>"></label><div><span class="muted">Iznos</span><div><strong><?= money_rs($s['planirani_iznos']) ?></strong> (100%)</div></div><div></div><button class="btn btn-primary">Sačuvaj plaćanje</button></form>
<?php else: ?>
<div class="payment-status <?= $statusPlacanja['kompletno']?'ok':'warn' ?>"><strong><?= $statusPlacanja['kompletno']?'Plan rata je kompletan.':'Plan rata nije kompletan.' ?></strong> Raspoređeno <?= money_rs($statusPlacanja['rasporedjeno']) ?> od <?= money_rs($statusPlacanja['ukupno']) ?><?php if(!$statusPlacanja['kompletno']): ?> · preostaje <?= money_rs($statusPlacanja['preostalo']) ?><?php endif; ?></div>
<form method="post" class="payment-grid"><input type="hidden" name="action" value="dodaj_ratu"><label>Datum rate<input type="date" name="datum_placanja" required></label><label>Iznos RSD<input type="number" step="0.01" min="0" name="iznos" placeholder="npr. 200000"></label><label>ili procenat %<input type="number" step="0.001" min="0" max="100" name="procenat" placeholder="npr. 30"></label><label>Napomena<input type="text" name="napomena" placeholder="Avans, II rata..."></label><div><button class="btn btn-primary">+ Dodaj ratu</button></div></form>
<?php if(!empty($statusPlacanja['sve_rate'])): ?><div class="table-wrap" style="margin-top:14px"><table><thead><tr><th>Datum</th><th>Udeo</th><th>Iznos</th><th>Napomena</th><th></th></tr></thead><tbody><?php foreach($statusPlacanja['sve_rate'] as $r):?><tr><td><?= e(date('d.m.Y.',strtotime($r['datum_placanja']))) ?></td><td><?= number_format((float)$r['procenat'],3,',','.') ?>%</td><td><strong><?= money_rs($r['iznos']) ?></strong></td><td><?= e($r['napomena']??'') ?></td><td><form method="post" onsubmit="return confirm('Obrisati ovu ratu?')"><input type="hidden" name="action" value="obrisi_ratu"><input type="hidden" name="rata_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-sm">Ukloni</button></form></td></tr><?php endforeach;?></tbody></table></div><?php endif; ?>
<?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__.'/../includes/footer.php'; ?>
