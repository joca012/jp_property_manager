<?php
ensure_program_odrzavanja_schema($conn);
$szId = get_int('sz_id');
$godina = get_int('godina', current_year());
$id = get_int('id');
$s = $id ? db_one($conn, "SELECT * FROM program_odrzavanja_stavke WHERE id=? AND sz_id=?", 'ii', [$id,$szId]) : null;
$title = $id ? 'Izmena stavke programa' : 'Nova stavka programa';
$subtitle = 'Definiši obavezu, prioritet, početak periodične stavke i dozvoljeni period izvođenja.';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $naziv = trim(post_value('naziv'));
    $kat = trim(post_value('kategorija','Ostalo'));
    $opis = trim(post_value('opis'));
    $uc = max(1,(int)post_value('ucestalost_meseci',12));
    $pr = post_value('prioritet','srednje');
    if (!in_array($pr,['kriticno','visoko','srednje','nisko'],true)) $pr='srednje';
    $pocetak = normalize_month(post_value('pocetni_mesec',1),1);
    $od = normalize_month(post_value('najraniji_mesec',$pocetak),$pocetak);
    $do = normalize_month(post_value('krajnji_mesec',12),12);
    $od = max($od,$pocetak);
    if ($do<$od) $do=$od;
    $prvi = sprintf('%04d-%02d-15',$godina,$pocetak);

    if ($naziv!=='') {
        if ($s) {
            $st=$conn->prepare("UPDATE program_odrzavanja_stavke SET naziv=?,kategorija=?,opis=?,ucestalost_meseci=?,prioritet=?,pocetni_mesec=?,najraniji_mesec=?,krajnji_mesec=?,prvi_datum=? WHERE id=? AND sz_id=?");
            $st->bind_param('sssisiiisii',$naziv,$kat,$opis,$uc,$pr,$pocetak,$od,$do,$prvi,$id,$szId);
        } else {
            $st=$conn->prepare("INSERT INTO program_odrzavanja_stavke(sz_id,naziv,kategorija,opis,ucestalost_meseci,prvi_datum,prioritet,pocetni_mesec,najraniji_mesec,krajnji_mesec) VALUES(?,?,?,?,?,?,?,?,?,?)");
            $st->bind_param('isssissiii',$szId,$naziv,$kat,$opis,$uc,$prvi,$pr,$pocetak,$od,$do);
        }
        $st->execute();
        redirect_to("index.php?page=program&sz_id=$szId&godina=$godina");
    }
}
require __DIR__.'/../includes/header.php';
?>
<section class="card narrow-card">
<div class="toolbar"><h2><?= $id?'Izmeni':'Dodaj' ?> stavku</h2><a class="btn btn-light" href="index.php?page=program&sz_id=<?= $szId ?>&godina=<?= $godina ?>">← Nazad</a></div>
<form method="post" class="form-grid">
    <div class="field full"><label>Naziv aktivnosti</label><input name="naziv" required value="<?= e($s['naziv']??'') ?>" placeholder="npr. Redovan servis lifta"></div>
    <div class="field"><label>Kategorija</label><input name="kategorija" value="<?= e($s['kategorija']??'Održavanje') ?>"></div>
    <div class="field"><label>Prioritet</label><select name="prioritet"><?php foreach(['kriticno'=>'Kritično / obavezno','visoko'=>'Visoko','srednje'=>'Srednje','nisko'=>'Nisko'] as $v=>$l):?><option value="<?= $v ?>" <?= (($s['prioritet']??'srednje')===$v)?'selected':'' ?>><?= $l ?></option><?php endforeach;?></select></div>
    <div class="field"><label>Mesec početka stavke</label><select name="pocetni_mesec"><?php for($m=1;$m<=12;$m++):?><option value="<?= $m ?>" <?= ((int)($s['pocetni_mesec']??($s['najraniji_mesec']??1))===$m)?'selected':'' ?>><?= e(mesec_naziv($m)) ?></option><?php endfor;?></select><small class="muted">Od ovog meseca počinje periodika ove stavke.</small></div>
    <div class="field"><label>Periodika (meseci)</label><input type="number" min="1" max="12" name="ucestalost_meseci" value="<?= e($s['ucestalost_meseci']??12) ?>"><small class="muted">1 = mesečno, 3 = tromesečno, 6 = polugodišnje, 12 = godišnje.</small></div>
    <div class="field"><label>Najraniji mesec izvođenja</label><select name="najraniji_mesec"><?php for($m=1;$m<=12;$m++):?><option value="<?= $m ?>" <?= ((int)($s['najraniji_mesec']??1)===$m)?'selected':'' ?>><?= e(mesec_naziv($m)) ?></option><?php endfor;?></select></div>
    <div class="field"><label>Krajnji mesec / rok</label><select name="krajnji_mesec"><?php for($m=1;$m<=12;$m++):?><option value="<?= $m ?>" <?= ((int)($s['krajnji_mesec']??12)===$m)?'selected':'' ?>><?= e(mesec_naziv($m)) ?></option><?php endfor;?></select></div>
    <div class="field full"><label>Opis / napomena</label><textarea name="opis" rows="4"><?= e($s['opis']??'') ?></textarea></div>
    <div class="notice full">Cena i izvođač se dodeljuju izborom ponude. Finansijski obračun počinje od kasnijeg od ova dva meseca: početak finansijskog plana ili početak ove stavke.</div>
    <div class="full actions"><button class="btn btn-primary">Sačuvaj</button></div>
</form>
</section>
<?php require __DIR__.'/../includes/footer.php'; ?>
