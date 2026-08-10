<?php
ensure_finansijski_plan_schema($conn);
$szId = get_int('sz_id');
$godina = isset($_GET['godina']) ? (int)$_GET['godina'] : current_year();
$id = get_int('id');
$z = db_one($conn, "SELECT * FROM stambene_zajednice WHERE id=?", 'i', [$szId]);
$plan = $z ? get_or_create_finansijski_plan($conn, $szId, $godina) : null;
if ($id && $plan) {
    $stmt = $conn->prepare("UPDATE finansijski_plan_stavke SET aktivna=0 WHERE id=? AND plan_id=?");
    $stmt->bind_param('ii', $id, $plan['id']);
    $stmt->execute();
}
redirect_to("index.php?page=finansijski_plan&sz_id=$szId&godina=$godina");
