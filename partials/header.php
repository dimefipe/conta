<?php
require_once __DIR__ . '/../lib/helpers.php';
$f = flash_get();
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
    <div class="container">
      <div class="brand">Contabilidad MVP</div>
      <nav class="nav">
        <a href="index.php">Dashboard</a>
        <a href="accounts.php">Plan de cuentas</a>
        <a href="entries.php">Libro diario</a>
        <a href="ledger.php">Libro mayor</a>
        <a href="reports_is.php">EE.RR.</a>
        <a href="reports_bs.php">Balance</a>
      </nav>
    </div>
  </div>

  <div class="container">
    <?php if ($f): ?>
      <div class="flash <?= h($f['type']) ?>"><?= h($f['msg']) ?></div>
    <?php endif; ?>
