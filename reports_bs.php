<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
$pdo = db();

$asOf = $_GET['as_of'] ?? date('Y-m-d');

function sum_balance(PDO $pdo, string $type, string $asOf): float {
  $st = $pdo->prepare("
    SELECT COALESCE(SUM(l.debit - l.credit),0) v
    FROM journal_lines l
    JOIN journal_entries e ON e.id=l.entry_id
    JOIN accounts a ON a.id=l.account_id
    WHERE e.status='POSTED' AND e.entry_date <= ? AND a.type=?
  ");
  $st->execute([$asOf,$type]);
  return (float)$st->fetch()['v'];
}

$assets = sum_balance($pdo,'ASSET',$asOf);
$liab   = sum_balance($pdo,'LIABILITY',$asOf);
$equity = sum_balance($pdo,'EQUITY',$asOf);

require __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Balance general</h2>

  <form class="card" method="get">
    <div class="row">
      <div class="field">
        <label>Fecha (as of)</label>
        <input type="date" name="as_of" value="<?= h($asOf) ?>">
      </div>
      <div class="field" style="align-self:flex-end">
        <button class="btn">Ver</button>
      </div>
    </div>
  </form>

  <table class="table">
    <tbody>
      <tr><td>Activos</td><td class="right">$<?= number_format($assets,2,',','.') ?></td></tr>
      <tr><td>Pasivos</td><td class="right">$<?= number_format($liab,2,',','.') ?></td></tr>
      <tr><td>Patrimonio</td><td class="right">$<?= number_format($equity,2,',','.') ?></td></tr>
      <tr><th>Pasivos + Patrimonio</th><th class="right">$<?= number_format(($liab + $equity),2,',','.') ?></th></tr>
      <tr><th>Diferencia (debería ~0)</th><th class="right">$<?= number_format(($assets - ($liab + $equity)),2,',','.') ?></th></tr>
    </tbody>
  </table>

  <div class="small">Nota: La diferencia se acerca a 0 si tu contabilidad está completa y bien clasificada.</div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
