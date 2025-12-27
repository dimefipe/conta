<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();

$pdo = db();

$shortcut = $_GET['p'] ?? '';
$ym = $_GET['ym'] ?? '';

if ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) {
  $from = $ym . '-01';
  $to = date('Y-m-t', strtotime($from));
} elseif (in_array($shortcut, ['week','month'], true)) {
  [$from,$to] = date_range_from_shortcut($shortcut);
} else {
  $from = $_GET['from'] ?? date('Y-m-01');
  $to   = $_GET['to']   ?? date('Y-m-t');
}

// UI Mes/Año
$years = range((int)date('Y') - 3, (int)date('Y') + 1);
$months = [
  '01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
  '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'
];
$currentYM = substr($from, 0, 7);

function sum_is(PDO $pdo, string $type, string $from, string $to): float {
  // Para INCOME: credit - debit
  // Para COST/EXPENSE: suele quedar negativo, lo convertimos a abs al mostrar
  $st = $pdo->prepare("
    SELECT COALESCE(SUM(l.credit - l.debit),0) v
    FROM journal_lines l
    JOIN journal_entries e ON e.id=l.entry_id
    JOIN accounts a ON a.id=l.account_id
    WHERE e.status='POSTED'
      AND e.entry_date BETWEEN ? AND ?
      AND a.type=?
  ");
  $st->execute([$from,$to,$type]);
  return (float)$st->fetch()['v'];
}

$income = sum_is($pdo,'INCOME',$from,$to);
$cost   = abs(sum_is($pdo,'COST',$from,$to));
$exp    = abs(sum_is($pdo,'EXPENSE',$from,$to));

$result = $income - $cost - $exp;

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <h2>Estado de resultados</h2>

  <form class="card" method="get">
    <div class="row">

      <div class="field">
        <label>Mes/Año rápido</label>
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
        <div class="small">Filtra 01 → último día del mes.</div>
      </div>

      <div class="field">
        <label>Desde</label>
        <input type="date" name="from" value="<?= h($from) ?>">
      </div>

      <div class="field">
        <label>Hasta</label>
        <input type="date" name="to" value="<?= h($to) ?>">
      </div>

      <div class="field" style="align-self:flex-end">
        <button class="btn">Filtrar</button>
      </div>

      <div class="field" style="align-self:flex-end">
        <a class="btn secondary" href="reports_is.php?p=week">Esta semana</a>
      </div>

      <div class="field" style="align-self:flex-end">
        <a class="btn secondary" href="reports_is.php?p=month">Este mes</a>
      </div>

    </div>
  </form>

  <div class="small">Periodo: <b><?= h($from) ?></b> a <b><?= h($to) ?></b></div>

  <table class="table" style="margin-top:10px">
    <tbody>
      <tr><td>Ingresos</td><td class="right"><?= clp($income) ?></td></tr>
      <tr><td>Costos</td><td class="right"><?= clp($cost) ?></td></tr>
      <tr><td>Gastos</td><td class="right"><?= clp($exp) ?></td></tr>
      <tr><th>Resultado</th><th class="right"><?= clp($result) ?></th></tr>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
