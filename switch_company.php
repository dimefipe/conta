<?php
require_once __DIR__ . '/lib/helpers.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect('index.php');
}

csrf_check($_POST['csrf'] ?? '');

$user = current_user();
$cid = (int)($_POST['company_id'] ?? 0);

if ($cid <= 0) {
  flash_set('err', 'Empresa inválida.');
  redirect('companies.php');
}

// valida acceso
require_company_access($cid);

// set company en sesión
set_company_id($cid);

flash_set('ok', 'Empresa activa actualizada.');
$back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
redirect($back);