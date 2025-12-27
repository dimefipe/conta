<?php
require_once __DIR__ . '/../lib/helpers.php';
require_login();

$f = flash_get();
$u = current_user();

$current = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Mapea páginas “hijas” al ítem del menú que deben marcar
$activeKey = match (true) {
  in_array($current, ['index.php'], true) => 'dashboard',
  in_array($current, ['accounts.php'], true) => 'accounts',
  in_array($current, ['entries.php','entry_new.php','entry_view.php','entry_void.php'], true) => 'entries',
  in_array($current, ['ledger.php'], true) => 'ledger',
  in_array($current, ['reports_is.php'], true) => 'is',
  in_array($current, ['reports_bs.php'], true) => 'bs',
  default => '',
};

function nav_item(string $href, string $label, string $key, string $activeKey): string {
  $cls = ($key === $activeKey) ? 'active' : '';
  return '<a class="'.$cls.'" href="'.$href.'">'.h($label).'</a>';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Contabilidad MVP</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body>

  <div class="topbar">
    <div class="topbar-inner">
      <div class="top-left">
        <button class="burger" id="burgerBtn" aria-label="Abrir menú">☰</button>
        <div class="brand">Contabilidad MVP</div>
      </div>

      <div class="top-actions">
        <?php if ($u): ?>
          <span class="small muted"><?= h($u['name']) ?></span>
          <a class="btn secondary" href="logout.php">Salir</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="overlay" id="overlay"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-head">
      <div class="brand">Menú</div>
      <button class="btn secondary" id="closeSidebar" type="button">Cerrar</button>
    </div>

    <nav class="side-nav">
      <?= nav_item('index.php', 'Dashboard', 'dashboard', $activeKey) ?>
      <?= nav_item('accounts.php', 'Plan de cuentas', 'accounts', $activeKey) ?>
      <?= nav_item('entries.php', 'Libro diario', 'entries', $activeKey) ?>
      <?= nav_item('ledger.php', 'Libro mayor', 'ledger', $activeKey) ?>
      <?= nav_item('reports_is.php', 'EE.RR.', 'is', $activeKey) ?>
      <?= nav_item('reports_bs.php', 'Balance', 'bs', $activeKey) ?>
    </nav>
  </aside>

  <div class="main-wrap">
    <div class="container">
      <?php if ($f): ?>
        <div class="flash <?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
      <?php endif; ?>
