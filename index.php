<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
$pdo = db();

[$fromMonth, $toMonth] = date_range_from_shortcut('month');

$income = $pdo->prepare("
  SELECT COALESCE(SUM(l.credit - l.debit),0) v
  FROM journal_lines l
  JOIN journal_entries e ON e.id=l.entry_id
  JOIN accounts a ON a.id=l.account_id
  WHERE e.status='POSTED' AND e.entry_date BETWEEN ? AND ? AND a.type='INCOME'
");
$income->execute([$fromMonth,$toMonth]);
$incomeV = (float)$income->fetch()['v'];

$expense = $pdo->prepare("
  SELECT COALESCE(SUM(l.debit - l.credit),0) v
  FROM journal_lines l
  JOIN journal_entries e ON e.id=l.entry_id
  JOIN accounts a ON a.id=l.account_id
  WHERE e.status='POSTED' AND e.entry_date BETWEEN ? AND ? AND a.type IN ('EXPENSE','COST')
");
$expense->execute([$fromMonth,$toMonth]);
$expenseV = (float)$expense->fetch()['v'];

$cash = $pdo->query("
  SELECT a.code,a.name,
    COALESCE(SUM(l.debit - l.credit),0) balance
  FROM accounts a
  LEFT JOIN journal_lines l ON l.account_id=a.id
  LEFT JOIN journal_entries e ON e.id=l.entry_id AND e.status='POSTED'
  WHERE a.code IN ('1101','1102')
  GROUP BY a.code,a.name
  ORDER BY a.code
")->fetchAll();

require __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Dashboard</h2>
  <div class="small">Mes actual: <?= h($fromMonth) ?> a <?= h($toMonth) ?></div>
  <div class="row" style="margin-top:12px">
    <div class="card kpi">
      <div class="small">Ingresos</div>
      <div class="v">$<?= number_format($incomeV,2,',','.') ?></div>
    </div>
    <div class="card kpi">
      <div class="small">Costos + Gastos</div>
      <div class="v">$<?= number_format($expenseV,2,',','.') ?></div>
    </div>
    <div class="card kpi">
      <div class="small">Resultado</div>
      <div class="v">$<?= number_format(($incomeV - $expenseV),2,',','.') ?></div>
    </div>
  </div>

  <hr />
  <h3>Saldos Caja/Banco</h3>
  <table class="table">
    <thead><tr><th>Cuenta</th><th class="right">Saldo</th></tr></thead>
    <tbody>
      <?php foreach ($cash as $r): ?>
        <tr>
          <td><?= h($r['code']) ?> — <?= h($r['name']) ?></td>
          <td class="right">$<?= number_format((float)$r['balance'],2,',','.') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div style="margin-top:12px">
    <a class="btn" href="entry_new.php">Crear asiento</a>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
