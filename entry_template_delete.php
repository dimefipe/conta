<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_login();
require_company_selected();

csrf_check($_POST['csrf'] ?? '');

$pdo = db();
$cid = (int) current_company_id();
$id  = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
  flash_set('err', 'Plantilla inválida.');
  redirect('entry_templates.php');
}

$st = $pdo->prepare("DELETE FROM entry_templates WHERE id = ? AND company_id = ?");
$st->execute([$id, $cid]);

flash_set('ok', 'Plantilla eliminada.');
redirect('entry_templates.php');
