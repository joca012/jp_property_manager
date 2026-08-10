<?php
$szId=get_int('sz_id');
$godina=get_int('godina',current_year());
ensure_program_odrzavanja_schema($conn);
$title='Program održavanja';
$subtitle='Plan radova, ponude, finansijska projekcija i realizacija.';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';
    $id=(int)($_POST['id']??0);

    if($action==='pomeri_termin'){
        $rez=program_pomeri_termin(
            $conn,
            (int)($_POST['termin_id']??0),
            (int)($_POST['stavka_id']??0),
            $szId,
            $godina,
            (int)($_POST['mesec']??0)
        );
        if(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest'){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($rez,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        redirect_to("index.php?page=program&sz_id=$szId&godina=$godina");
    }

    if($action==='obrisi'&&$id){
        $stmt=$conn->prepare("UPDATE program_odrzavanja_stavke SET aktivna=0 WHERE id=? AND sz_id=?");
        $stmt->bind_param('ii',$id,$szId);
        $stmt->execute();
        redirect_to("index.php?page=program&sz_id=$szId&godina=$godina");
    }

    if($action==='realizuj'&&$id){
        $tid=(int)($_POST['termin_id']??0);
        if($tid){
            $stmt=$conn->prepare("UPDATE program_odrzavanja_termini SET status='izvrseno',predlozen=0 WHERE id=? AND stavka_id=?");
            $stmt->bind_param('ii',$tid,$id);
            $stmt->execute();
        }
        redirect_to("index.php?page=program&sz_id=$szId&godina=$godina");
    }

    if($action==='predlozi'){
        $n=predlozi_termine_programa($conn,$szId,$godina);
        redirect_to("index.php?page=program&sz_id=$szId&godina=$godina&predlozeno=$n");
    }

    if($action==='potvrdi'&&$id){
        $tid=(int)($_POST['termin_id']??0);
        if($tid){
            $stmt=$conn->prepare("UPDATE program_odrzavanja_termini t JOIN program_odrzavanja_stavke s ON s.id=t.stavka_id SET t.predlozen=0 WHERE t.id=? AND t.stavka_id=? AND s.sz_id=? AND t.status='planirano'");
            $stmt->bind_param('iii',$tid,$id,$szId);
            $stmt->execute();
        }
        redirect_to("index.php?page=program&sz_id=$szId&godina=$godina");
    }

    if($action==='otkljucaj'&&$id){
        $tid=(int)($_POST['termin_id']??0);
        if($tid){
            $stmt=$conn->prepare("UPDATE program_odrzavanja_termini t JOIN program_odrzavanja_stavke s ON s.id=t.stavka_id SET t.predlozen=1 WHERE t.id=? AND t.stavka_id=? AND s.sz_id=? AND t.status='planirano'");
            $stmt->bind_param('iii',$tid,$id,$szId);
            $stmt->execute();
        }
        redirect_to("index.php?page=program&sz_id=$szId&godina=$godina");
    }

    if($action==='potvrdi_sve_stavke'&&$id){
        $stmt=$conn->prepare("UPDATE program_odrzavanja_termini t JOIN program_odrzavanja_stavke s ON s.id=t.stavka_id SET t.predlozen=0 WHERE s.id=? AND s.sz_id=? AND YEAR(t.datum)=? AND t.status='planirano'");
        $stmt->bind_param('iii',$id,$szId,$godina);
        $stmt->execute();
        redirect_to("index.php?page=program&sz_id=$szId&godina=$godina");
    }

    if($action==='otkljucaj_sve_stavke'&&$id){
        $stmt=$conn->prepare("UPDATE program_odrzavanja_termini t JOIN program_odrzavanja_stavke s ON s.id=t.stavka_id SET t.predlozen=1 WHERE s.id=? AND s.sz_id=? AND YEAR(t.datum)=? AND t.status='planirano'");
        $stmt->bind_param('iii',$id,$szId,$godina);
        $stmt->execute();
        redirect_to("index.php?page=program&sz_id=$szId&godina=$godina");
    }

    if($action==='potvrdi_sve_kalendar'){
        $stmt=$conn->prepare("UPDATE program_odrzavanja_termini t JOIN program_odrzavanja_stavke s ON s.id=t.stavka_id SET t.predlozen=0 WHERE s.sz_id=? AND s.aktivna=1 AND YEAR(t.datum)=? AND t.status='planirano'");
        $stmt->bind_param('ii',$szId,$godina);
        $stmt->execute();
        redirect_to("index.php?page=program&sz_id=$szId&godina=$godina");
    }

    if($action==='otkljucaj_sve_kalendar'){
        $stmt=$conn->prepare("UPDATE program_odrzavanja_termini t JOIN program_odrzavanja_stavke s ON s.id=t.stavka_id SET t.predlozen=1 WHERE s.sz_id=? AND s.aktivna=1 AND YEAR(t.datum)=? AND t.status='planirano'");
        $stmt->bind_param('ii',$szId,$godina);
        $stmt->execute();
        redirect_to("index.php?page=program&sz_id=$szId&godina=$godina");
    }
}

$stavke=program_stavke_sa_cenama($conn,$szId,$godina);
foreach($stavke as &$s){
    $s['termini']=program_termini_stavke($conn,(int)$s['id'],$godina);
    $obracun=program_godisnji_iznos_stavke($conn,(int)$s['id'],$godina,(float)$s['planirani_iznos']);
    $s['broj_termina']=$obracun['broj_termina'];
    $s['godisnji_iznos']=$obracun['ukupno'];
    $s['placanje']=program_plan_placanja_status($conn,$s,null);
    $s['placanja_godina']=program_placanja_stavke($conn,(int)$s['id'],$godina);
    $ukRate=count($s['placanja_godina']);
    foreach($s['placanja_godina'] as $ri=>&$rr){$rr['_redni_broj']=$ri+1;$rr['_ukupno_rata']=$ukRate;} unset($rr);
}
unset($s);
$summary=finansijski_plan_summary($conn,$szId,$godina);
require __DIR__.'/../includes/header.php';
?>

<style>
.program-calendar{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:stretch;width:100%;max-width:100%;overflow:visible}.program-calendar .month{display:flex;flex-direction:column;min-width:0;min-height:250px;transition:.15s ease}.program-calendar .month.drag-over{outline:2px dashed #2563eb;background:#eff6ff}.calendar-events{display:flex;flex-direction:column;gap:6px;min-height:80px;flex:1}.calendar-event{display:block;width:100%;border:0;border-radius:9px;background:#eef4ff;color:#1d4ed8;padding:7px 8px;text-align:left;font:inherit;font-size:12px;font-weight:700;cursor:pointer}.calendar-event[draggable="true"]{cursor:grab}.calendar-event[draggable="true"]:active{cursor:grabbing}.calendar-event.executed{background:#ecfdf3;color:#067647}.calendar-event.proposed{box-shadow:inset 0 0 0 1px #93c5fd}.calendar-event.no-offer{background:#fff1f2;color:#b42318;box-shadow:inset 0 0 0 1px #fda4af}.calendar-event.payment-warning{background:#fff7ed;color:#9a3412;box-shadow:inset 0 0 0 1px #fdba74}.calendar-event.work-event::before{content:'🔧 ';}.calendar-payment{display:block;width:100%;border:1px dashed #94a3b8;border-radius:8px;background:#f8fafc;color:#475569;padding:5px 7px;text-align:left;font-size:11px;line-height:1.3}.calendar-payment strong{color:#334155}.calendar-payment::before{content:'💳 ';}.program-row-no-offer{background:#fff6f7}.program-row-payment-warning{background:#fffbeb}.status-bad{display:inline-block;background:#fee2e2;color:#b42318;border-radius:999px;padding:3px 7px;font-size:11px;font-weight:800}.status-warn{display:inline-block;background:#ffedd5;color:#9a3412;border-radius:999px;padding:3px 7px;font-size:11px;font-weight:800}.calendar-balance{border-top:1px solid var(--line);padding-top:8px;margin-top:10px;font-size:11px;line-height:1.45}.calendar-balance div{display:flex;justify-content:space-between;gap:5px}.calendar-balance .saldo{font-weight:800;font-size:12px;margin-top:3px}.calendar-balance.negative .saldo{color:#b42318;animation:saldoBlink 1s step-end infinite}.calendar-balance.negative{background:#fff1f0;border-radius:8px;padding:7px;margin-left:-3px;margin-right:-3px}@keyframes saldoBlink{50%{opacity:.25}}.event-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.48);display:none;align-items:center;justify-content:center;padding:20px;z-index:9999}.event-modal-backdrop.open{display:flex}.event-modal{width:min(500px,100%);background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(15,23,42,.25);padding:20px}.event-modal h3{margin:0 0 14px}.event-modal-grid{display:grid;grid-template-columns:140px 1fr;gap:8px 12px;font-size:14px}.event-modal-grid b{color:#475467}.event-modal-actions{display:flex;justify-content:flex-end;margin-top:18px}@media(max-width:1200px){.program-calendar{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.program-calendar{grid-template-columns:1fr}.program-calendar .month{min-height:220px}}
</style>

<?php if(isset($_GET['predlozeno'])):?>
<section class="card" style="border-left:4px solid green">Predloženo je <?= (int)$_GET['predlozeno'] ?> novih termina. Predloge potvrdi pre realizacije.</section>
<?php endif;?>

<section class="card">
    <div class="toolbar">
        <h2>Program održavanja <?= $godina ?></h2>
        <div class="actions">
            <a class="btn btn-light" target="_blank" href="index.php?page=izvoz_pdf&tip=program&sz_id=<?= $szId ?>&godina=<?= $godina ?>&auto=1">PDF / štampa</a>
            <a class="btn btn-primary" href="index.php?page=program_stavka&sz_id=<?= $szId ?>&godina=<?= $godina ?>">+ Dodaj stavku</a>
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="predlozi">
                <button class="btn btn-warning" type="submit">Predloži termine prema finansijama</button>
            </form>
        </div>
    </div>
    <p class="muted">🔧 označava termin aktivnosti, a 💳 termin plaćanja/rate. Klik na aktivnost prikazuje cenu i detalje. Drag-drop je omogućen samo za stavke koje nisu mesečne. Finansijski nedovoljan termin se može postaviti, ali će saldo biti označen trepćućim crvenim upozorenjem.</p>
</section>

<section class="card" style="margin-top:18px">
    <div class="toolbar">
        <div><h2 style="margin-bottom:4px">Kalendar</h2><span class="badge">prilivi / odlivi / saldo po mesecima</span></div>
        <div class="actions">
            <form method="post" onsubmit="return confirm('Potvrditi i zaključati sve trenutno planirane termine u kalendaru?')"><input type="hidden" name="action" value="potvrdi_sve_kalendar"><button class="btn btn-primary btn-sm" type="submit">Potvrdi sve termine</button></form>
            <form method="post" onsubmit="return confirm('Otključati sve planirane termine? Predloženi termini će zatim ponovo moći da se preračunaju, a nemesečni i da se pomeraju.')"><input type="hidden" name="action" value="otkljucaj_sve_kalendar"><button class="btn btn-light btn-sm" type="submit">Otključaj sve termine</button></form>
        </div>
    </div>
    <div class="timeline program-calendar">
    <?php for($m=1;$m<=12;$m++): $mon=$summary['monthly'][$m]??['ocekivani_priliv'=>0,'odliv'=>0,'stanje'=>0]; $neg=(float)$mon['stanje']<0; ?>
        <div class="month calendar-month" data-month="<?= $m ?>">
            <strong><?= e(mesec_naziv($m)) ?></strong>
            <div class="calendar-events">
            <?php $ima=false; ?>
            <?php foreach($stavke as $s): ?>
                <?php foreach($s['termini'] as $t): if((int)date('n',strtotime($t['datum']))!==$m) continue; $ima=true;
                    $monthly=((int)$s['ucestalost_meseci']===1);
                    $draggable=(!$monthly && $t['status']==='planirano' && (int)$t['predlozen']===1);
                    $bezCene=!program_stavka_ima_cenu($s);
                    $placanjeNepotpuno=(!$bezCene && !($s['placanje']['kompletno']??true));
                ?>
                    <button type="button"
                        class="calendar-event work-event<?= $t['predlozen']?' proposed':'' ?><?= $t['status']==='izvrseno'?' executed':'' ?><?= $bezCene?' no-offer':($placanjeNepotpuno?' payment-warning':'') ?>"
                        draggable="<?= $draggable?'true':'false' ?>"
                        data-termin-id="<?= (int)$t['id'] ?>"
                        data-stavka-id="<?= (int)$s['id'] ?>"
                        data-title="<?= e($s['naziv']) ?>"
                        data-price="<?= e($bezCene?'Cena nije definisana':money_rs((float)$s['planirani_iznos'])) ?>"
                        data-contractor="<?= e($s['izvodjac_naziv']??'Nije izabran') ?>"
                        data-date="<?= e(date('d.m.Y.',strtotime($t['datum']))) ?>"
                        data-status="<?= e($t['status']==='izvrseno'?'Izvršeno':($t['predlozen']?'Predloženo':'Potvrđeno')) ?>"
                        data-period="<?= (int)$s['ucestalost_meseci']===1?'Mesečno':'Na svakih '.(int)$s['ucestalost_meseci'].' mes.' ?>"
                        data-draggable="<?= $draggable?'1':'0' ?>"
                        title="Termin aktivnosti · klik za detalje<?= $draggable?' · prevuci za promenu meseca':'' ?>">
                        <?= e($s['naziv']) ?>
                    </button>
                <?php endforeach; ?>
                <?php if(($s['nacin_placanja']??'po_terminu')!=='po_terminu'): foreach($s['placanja_godina'] as $rp): if((int)date('n',strtotime($rp['datum_placanja']))!==$m) continue; $ima=true; ?>
                    <div class="calendar-payment" title="Ovo je termin plaćanja, ne termin radova.">
                        <strong><?= e($s['naziv']) ?></strong><br>
                        <?php if($s['nacin_placanja']==='rate'): ?><span class="muted">Rata <?= (int)$rp['_redni_broj'] ?>/<?= (int)$rp['_ukupno_rata'] ?> · </span><?php endif; ?><?= money_rs((float)$rp['iznos']) ?> · <?= e(date('d.m.Y.',strtotime($rp['datum_placanja']))) ?>
                        <?php if(!empty($rp['napomena'])): ?><div class="muted"><?= e($rp['napomena']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; endif; ?>
            <?php endforeach; ?>
            <?php if(!$ima): ?><span class="muted">Nema radova</span><?php endif; ?>
            </div>
            <div class="calendar-balance<?= $neg?' negative':'' ?>" id="balance-<?= $m ?>">
                <div><span>Prilivi</span><b><?= money_rs((float)$mon['ocekivani_priliv']) ?></b></div>
                <div><span>Odlivi</span><b><?= money_rs((float)$mon['odliv']) ?></b></div>
                <div class="saldo"><span>Saldo</span><b><?= money_rs((float)$mon['stanje']) ?></b></div>
            </div>
        </div>
    <?php endfor; ?>
    </div>
</section>

<section class="card" style="margin-top:18px">
    <div class="toolbar"><h2>Stavke programa</h2><span class="badge"><?= count($stavke) ?> aktivnih</span></div>
    <?php if(!$stavke): ?>
        <div class="empty">Program još nije formiran. Dodaj prvu stavku.</div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Aktivnost</th><th>Prioritet / periodika</th><th>Ponuda / izvođač</th><th>Vrednost</th><th>Termini</th><th>Akcije</th></tr></thead>
        <tbody>
        <?php foreach($stavke as $s): ?>
            <?php $bezCene=!program_stavka_ima_cenu($s); $placanjeNepotpuno=(!$bezCene && !($s['placanje']['kompletno']??true)); ?>
            <tr class="<?= $bezCene?'program-row-no-offer':($placanjeNepotpuno?'program-row-payment-warning':'') ?>">
                <td><strong><?= e($s['naziv']) ?></strong><div class="muted"><?= e($s['kategorija']) ?></div><?php if($bezCene): ?><div style="margin-top:5px"><span class="status-bad">CENA NIJE DEFINISANA</span></div><?php elseif($placanjeNepotpuno): ?><div style="margin-top:5px"><span class="status-warn">PLAN PLAĆANJA NIJE KOMPLETAN</span></div><?php endif; ?></td>
                <td>
                    <strong><?= e(ucfirst($s['prioritet'])) ?></strong>
                    <div class="muted">Početak: <?= e(mesec_kratko((int)($s['pocetni_mesec'] ?? $s['najraniji_mesec']))) ?></div>
                    <div class="muted">Rok/prozor: <?= e(mesec_kratko($s['najraniji_mesec'])) ?>–<?= e(mesec_kratko($s['krajnji_mesec'])) ?></div>
                    <div class="muted">na svakih <?= (int)$s['ucestalost_meseci'] ?> mes.</div>
                </td>
                <td><?php if($bezCene): ?><strong style="color:#b42318">Cena nije definisana</strong><div class="muted">Izaberi ponudu, cenovnik ili procenu.</div><?php else: ?><strong><?= e($s['izvor_cene_naziv']??program_izvor_cene_label($s)) ?></strong><div class="muted"><?= e(program_izvor_cene_label($s)) ?><?= !empty($s['izvodjac_naziv'])?' · '.e($s['izvodjac_naziv']):'' ?></div><?php if(($s['izvor_cene']??'')==='cenovnik'): ?><div class="muted"><?= e($s['kolicina']) ?> × <?= money_rs($s['jedinicna_cena']) ?><?= !empty($s['cenovnik_jedinica'])?' / '.e($s['cenovnik_jedinica']):'' ?></div><?php endif; ?><div class="muted"><?= ($s['nacin_placanja']??'po_terminu')==='po_terminu'?'Plaćanje po terminu':(($s['nacin_placanja']??'')==='jednokratno'?'Jednokratno plaćanje':'Plaćanje na rate') ?></div><?php endif; ?></td>
                <td>
                    <?php if($bezCene): ?>
                        <strong style="color:#b42318">—</strong>
                    <?php elseif(($s['nacin_placanja']??'po_terminu')==='po_terminu'): ?>
                        <strong><?= money_rs($s['planirani_iznos']) ?> / termin</strong>
                        <?php if($s['broj_termina']): ?><div class="muted">Ukupno <?= money_rs($s['godisnji_iznos']) ?></div><?php endif; ?>
                    <?php else: ?>
                        <strong><?= money_rs($s['planirani_iznos']) ?></strong>
                        <div class="muted">Raspoređeno za plaćanje: <?= money_rs((float)($s['placanje']['rasporedjeno']??0)) ?></div>
                        <?php if($placanjeNepotpuno): ?><div style="color:#9a3412">Preostaje: <?= money_rs((float)($s['placanje']['preostalo']??0)) ?></div><?php endif; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if(!$s['termini']): ?>
                        <span class="muted">Nije zakazano</span>
                    <?php else:
                        $prviTermin = $s['termini'][0];
                        $poslednjiTermin = $s['termini'][count($s['termini'])-1];
                        $brojPredlozenih = count(array_filter($s['termini'], fn($t)=>(int)$t['predlozen']===1));
                        $brojIzvrsenih = count(array_filter($s['termini'], fn($t)=>$t['status']==='izvrseno'));
                    ?>
                        <strong><?= count($s['termini']) ?> termina</strong>
                        <div class="muted">
                            <?= e(mesec_kratko((int)date('n',strtotime($prviTermin['datum'])))) ?>–<?= e(mesec_kratko((int)date('n',strtotime($poslednjiTermin['datum'])))) ?>
                            <?php if($brojPredlozenih): ?> · <?= $brojPredlozenih ?> predloženo<?php endif; ?>
                            <?php if($brojIzvrsenih): ?> · <?= $brojIzvrsenih ?> izvršeno<?php endif; ?>
                        </div>
                        <details style="margin-top:6px">
                            <summary style="cursor:pointer;color:#2455d6;font-weight:600">Prikaži termine i akcije</summary>
                            <div class="actions" style="margin-top:9px;margin-bottom:5px">
                                <?php if($brojPredlozenih): ?>
                                <form method="post" onsubmit="return confirm('Potvrditi sve planirane termine ove stavke?')"><input type="hidden" name="action" value="potvrdi_sve_stavke"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-primary btn-sm" type="submit">Potvrdi sve termine</button></form>
                                <?php endif; ?>
                                <?php $brojPotvrdjenih = count(array_filter($s['termini'], fn($t)=>$t['status']==='planirano' && (int)$t['predlozen']===0)); if($brojPotvrdjenih): ?>
                                <form method="post" onsubmit="return confirm('Otključati sve potvrđene termine ove stavke?')"><input type="hidden" name="action" value="otkljucaj_sve_stavke"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-light btn-sm" type="submit">Otključaj sve termine</button></form>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top:8px">
                            <?php foreach($s['termini'] as $t): ?>
                                <div style="padding:7px 0;border-top:1px solid #edf1f7">
                                    <strong><?= e(date('d.m.Y.',strtotime($t['datum']))) ?></strong>
                                    <span class="muted"> · <?= $t['status']==='izvrseno'?'Izvršeno':($t['predlozen']?'Predloženo':'Potvrđeno') ?></span>
                                    <?php if($t['status']!=='izvrseno'): ?>
                                    <div class="actions" style="margin-top:4px">
                                        <?php if($t['predlozen']): ?>
                                        <form method="post"><input type="hidden" name="action" value="potvrdi"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="termin_id" value="<?= $t['id'] ?>"><button class="btn btn-primary btn-sm">Potvrdi</button></form>
                                        <?php else: ?>
                                        <form method="post"><input type="hidden" name="action" value="otkljucaj"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="termin_id" value="<?= $t['id'] ?>"><button class="btn btn-light btn-sm">Otključaj</button></form>
                                        <?php endif; ?>
                                        <form method="post"><input type="hidden" name="action" value="realizuj"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="termin_id" value="<?= $t['id'] ?>"><button class="btn btn-success btn-sm">Realizuj</button></form>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </td>
                <td><div class="actions">
                    <a class="btn btn-light btn-sm" href="index.php?page=program_ponude&program_id=<?= (int)$s['id'] ?>&sz_id=<?= $szId ?>&godina=<?= $godina ?>">Cena / plaćanje</a>
                    <a class="btn btn-light btn-sm" href="index.php?page=program_stavka&id=<?= (int)$s['id'] ?>&sz_id=<?= $szId ?>&godina=<?= $godina ?>">Izmeni</a>
                    <form method="post" onsubmit="return confirm('Ukloniti stavku programa?')"><input type="hidden" name="action" value="obrisi"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-danger btn-sm">Ukloni</button></form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>

<div class="event-modal-backdrop" id="eventModal" aria-hidden="true">
    <div class="event-modal" role="dialog" aria-modal="true" aria-labelledby="eventModalTitle">
        <h3 id="eventModalTitle">Detalji aktivnosti</h3>
        <div class="event-modal-grid">
            <b>Cena</b><span id="modalPrice"></span>
            <b>Izvođač</b><span id="modalContractor"></span>
            <b>Termin</b><span id="modalDate"></span>
            <b>Status</b><span id="modalStatus"></span>
            <b>Periodika</b><span id="modalPeriod"></span>
        </div>
        <div class="event-modal-actions"><button type="button" class="btn btn-light" id="closeEventModal">Zatvori</button></div>
    </div>
</div>

<script>
(function(){
    const modal=document.getElementById('eventModal');
    const title=document.getElementById('eventModalTitle');
    let dragged=null;

    document.querySelectorAll('.calendar-event').forEach(el=>{
        el.addEventListener('click',()=>{
            title.textContent=el.dataset.title || 'Detalji aktivnosti';
            document.getElementById('modalPrice').textContent=el.dataset.price || '—';
            document.getElementById('modalContractor').textContent=el.dataset.contractor || '—';
            document.getElementById('modalDate').textContent=el.dataset.date || '—';
            document.getElementById('modalStatus').textContent=el.dataset.status || '—';
            document.getElementById('modalPeriod').textContent=el.dataset.period || '—';
            modal.classList.add('open'); modal.setAttribute('aria-hidden','false');
        });
        if(el.draggable){
            el.addEventListener('dragstart',e=>{
                dragged=el;
                e.dataTransfer.effectAllowed='move';
                e.dataTransfer.setData('text/plain',el.dataset.terminId);
            });
            el.addEventListener('dragend',()=>{ dragged=null; document.querySelectorAll('.calendar-month').forEach(m=>m.classList.remove('drag-over')); });
        }
    });
    document.getElementById('closeEventModal').addEventListener('click',()=>{modal.classList.remove('open');modal.setAttribute('aria-hidden','true');});
    modal.addEventListener('click',e=>{if(e.target===modal){modal.classList.remove('open');modal.setAttribute('aria-hidden','true');}});

    document.querySelectorAll('.calendar-month').forEach(month=>{
        month.addEventListener('dragover',e=>{ if(!dragged) return; e.preventDefault(); e.dataTransfer.dropEffect='move'; month.classList.add('drag-over'); });
        month.addEventListener('dragleave',()=>month.classList.remove('drag-over'));
        month.addEventListener('drop',async e=>{
            e.preventDefault(); month.classList.remove('drag-over');
            if(!dragged) return;
            const fd=new FormData();
            fd.append('action','pomeri_termin');
            fd.append('termin_id',dragged.dataset.terminId);
            fd.append('stavka_id',dragged.dataset.stavkaId);
            fd.append('mesec',month.dataset.month);
            try{
                const r=await fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
                const data=await r.json();
                if(!data.ok){ alert(data.poruka || 'Termin nije moguće pomeriti.'); return; }
                window.location.reload();
            }catch(err){ alert('Greška pri pomeranju termina.'); }
        });
    });
})();
</script>

<?php require __DIR__.'/../includes/footer.php'; ?>
