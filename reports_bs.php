<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();

$pdo = db();

$ym = $_GET['ym'] ?? '';
$asOf = $_GET['as_of'] ?? date('Y-m-d');

// Si viene ym, usamos el último día de ese mes como asOf (ideal para balances mensuales)
if ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) {
  $asOf = date('Y-m-t', strtotime($ym . '-01'));
}

// UI helpers Mes/Año
$years = range((int)date('Y') - 3, (int)date('Y') + 1);
$months = [
  '01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
  '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'
];

$currentYM = substr($asOf, 0, 7);

function sum_balance(PDO $pdo, string $type, string $asOf): float {
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

$assets = sum_balance($pdo,'ASSET',$asOf);
$liab   = sum_balance($pdo,'LIABILITY',$asOf);
$equity = sum_balance($pdo,'EQUITY',$asOf);

$diff = $assets - ($liab + $equity);

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <h2>Balance general</h2>

  <form class="card" method="get">
    <div class="row">

      <!-- Mes/Año rápido (usa fin de mes como fecha) -->
      <div class="field">
        <label>Mes/Año rápido (fin de mes)</label>
        <select name="ym" onchange="this.form.submit()">
          <option value="">— Elegir —</option>
          <?php foreach ($years as $y): ?>
            <?php foreach ($months as $m => $label): $val = $y . '-' . $m; ?>
              <option value="<?= h($val) ?>" <?= ($currentYM === $val) ? 'selected' : '' ?>>
                <?= h($label) ?> <?= h((string)$y) ?>
              </option>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </select>
        <div class="small">Selecciona un mes y calcula el balance al último día de ese mes.</div>
      </div>

      <!-- Fecha manual -->
      <div class="field">
        <label>Fecha (as of)</label>
        <input type="date" name="as_of" value="<?= h($asOf) ?>">
      </div>

      <div class="field" style="align-self:flex-end">
        <button class="btn">Ver</button>
      </div>

    </div>
  </form>

  <div class="small">Balance al: <b><?= h($asOf) ?></b></div>

  <table class="table" style="margin-top:10px">
    <tbody>
      <tr>
        <td>Activos</td>
        <td class="right">$<?= number_format($assets,2,',','.') ?></td>
      </tr>
      <tr>
        <td>Pasivos</td>
        <td class="right">$<?= number_format($liab,2,',','.') ?></td>
      </tr>
      <tr>
        <td>Patrimonio</td>
        <td class="right">$<?= number_format($equity,2,',','.') ?></td>
      </tr>
      <tr>
        <th>Pasivos + Patrimonio</th>
        <th class="right">$<?= number_format(($liab + $equity),2,',','.') ?></th>
      </tr>
      <tr>
        <th>Diferencia (ideal ~0)</th>
        <th class="right">$<?= number_format($diff,2,',','.') ?></th>
      </tr>
    </tbody>
  </table>

  <div class="small">
    Tip: si la diferencia no es cercana a 0, normalmente falta registrar capital/resultado acumulado,
    o hay cuentas mal clasificadas.
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
