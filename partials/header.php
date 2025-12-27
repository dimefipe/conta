<?php
require_once __DIR__ . '/../lib/helpers.php';
require_login();

$f = flash_get();
$u = current_user();
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
    <div class="container topbar-inner">
      <button class="burger" id="burgerBtn" aria-label="Abrir menú">☰</button>
      <div class="brand">Contabilidad MVP</div>

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
      <a href="index.php">Dashboard</a>
      <a href="accounts.php">Plan de cuentas</a>
      <a href="entries.php">Libro diario</a>
      <a href="ledger.php">Libro mayor</a>
      <a href="reports_is.php">EE.RR.</a>
      <a href="reports_bs.php">Balance</a>
    </nav>
  </aside>

  <!-- IMPORTANTE: wrapper para que en escritorio el contenido quede al costado -->
  <div class="main-wrap">
    <div class="container">
      <?php if ($f): ?>
        <div class="flash <?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
      <?php endif; ?>
