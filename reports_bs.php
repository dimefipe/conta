<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();

$pdo = db();

$ym = $_GET['ym'] ?? '';
$asOf = $_GET['as_of'] ?? date('Y-m-d');

if ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) {
  $asOf = date('Y-m-t', strtotime($ym . '-01')); // fin de mes
}

// UI Mes/Año
$years = range((int)date('Y') - 3, (int)date('Y') + 1);
$months = [
  '01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
  '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'
];
$currentYM = substr($asOf, 0, 7);

function sum_bs(PDO $pdo, string $type, string $asOf): float {
  // Para BS usamos debit - credit acumulado a la fecha
  $st = $pdo->prepare("
    SELECT COALESCE(SUM(l.debit - l.credit),0) v
    FROM journal_lines l
    JOIN journal_entries e ON e.id=l.entry_id
    JOIN accounts a ON a.id=l.account_id
    WHERE e.status='POSTED'
      AND e.entry_date <= ?
      AND a.type=?
  ");
  $st->execute([$asOf,$type]);
  return (float)$st->fetch()['v'];
}

$assets = sum_bs($pdo,'ASSET',$asOf);
$liab   = sum_bs($pdo,'LIABILITY',$asOf);
$equity = sum_bs($pdo,'EQUITY',$asOf);

$totalLE = $liab + $equity;
$diff = $assets - $totalLE;

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <h2>Balance general</h2>

  <form class="card" method="get">
    <div class="row">

      <div class="field">
        <label>Mes/Año rápido (fin de mes)</label>
        <select name="ym" onchange="this.form.submit()">
          <option value="">— Elegir —</option>
          <?php foreach ($years as $y): ?>
            <?php foreach ($months as $m=>$label): $val=$y.'-'.$m; ?>
              <option value="<?= h($val) ?>" <?= ($currentYM === $val) ? 'selected' : '' ?>>
                <?= h($label) ?> <?= h((string)$y) ?>
              </option>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </select>
        <div class="small">Selecciona un mes y calcula el balance al último día del mes.</div>
      </div>

      <div class="field">
        <label>Fecha (as of)</label>
        <input type="date" name="as_of" value="<?= h($asOf) ?>">
      </div>

      <div class="field" style="align-self:flex-end">
        <button class="btn">Ver</button>
      </div>

    </div>
  </form>

  <div class="
