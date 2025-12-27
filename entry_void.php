<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();
csrf_check($_GET['csrf'] ?? '');
$pdo = db();

$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("UPDATE journal_entries SET status='VOID', voided_at=NOW() WHERE id=? AND status='POSTED'");
$st->execute([$id]);

flash_set('ok',"Asiento #$id anulado.");
redirect('entries.php');
