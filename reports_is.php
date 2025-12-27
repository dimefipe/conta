<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
$pdo = db();

$shortcut = $_GET['p'] ?? '';
if (in_array($shortcut,['week','month'],true)) {
  [$from,$to] = date_range_from_shortcut($shortcut);
} else {
  $from = $_GET['from'] ?? date('Y-m-01');
  $to   = $_GET['to'] ?? date('Y-m-t');
}

function sum_by_type(PDO $pdo, string $type, string $from, string $to): float {
  $st = $pdo->prepare("
    SELECT COALESCE(SUM(l.credit - l.debit),0) v
    FROM journal_lines l
    JOIN journal_entries e ON e.id=l.entry_id
    JOIN accounts a ON a.id=l.account_id
    WHERE e.status='POSTED' AND e.entry_date BETWEEN ? AND ? AND a.type=?
  ");
  $st->execute([$from,$to,$type]);
  return (float)$st->fetch()['v'];
}

$income = sum_by_type($pdo,'INCOME',$from,$to);
$cost   = sum_by_type($pdo,'COST',$from,$to);     // COST normalmente va al debe, por eso puede venir negativo; lo normalizamos abajo
$exp    = sum_by_type($pdo,'EXPENSE',$from,$to);

$costAbs = abs($cost);
$expAbs  = abs($exp);

$result = $income - $costAbs - $expAbs;

require __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Estado de resultados</h2>

  <form class="card" method="get">
    <div class="row">
      <div class="field"><label>Desde</label><input type="date" name="from" value="<?= h($from) ?>"></div>
      <div class="field"><label>Hasta</label><input type="date" name="to" value="<?= h($to) ?>"></div>
      <div class="field" style="align-self:flex-end"><button class="btn">Filtrar</button></div>
      <div class="field" style="align-self:flex-end"><a class="btn secondary" href="reports_is.php?p=week">Esta semana</a></div>
      <div class="field" style="align-self:flex-end"><a class="btn secondary" href="reports_is.php?p=month">Este mes</a></div>
    </div>
  </form>

  <table class="table">
    <tbody>
      <tr><td>Ingresos</td><td class="right">$<?= number_format($income,2,',','.') ?></td></tr>
      <tr><td>Costos</td><td class="right">$<?= number_format($costAbs,2,',','.') ?></td></tr>
      <tr><td>Gastos</td><td class="right">$<?= number_format($expAbs,2,',','.') ?></td></tr>
      <tr><th>Resultado</th><th class="right">$<?= number_format($result,2,',','.') ?></th></tr>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
