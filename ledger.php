<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
$pdo = db();

$accounts = $pdo->query("SELECT id,code,name FROM accounts WHERE is_active=1 ORDER BY code")->fetchAll();

$accountId = (int)($_GET['account_id'] ?? 0);
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-t');

$movs = [];
$acc = null;
$saldoInicial = 0;

if ($accountId > 0) {
  $accSt = $pdo->prepare("SELECT * FROM accounts WHERE id=?");
  $accSt->execute([$accountId]);
  $acc = $accSt->fetch();

  $si = $pdo->prepare("
    SELECT COALESCE(SUM(l.debit - l.credit),0) v
    FROM journal_lines l
    JOIN journal_entries e ON e.id=l.entry_id
    WHERE e.status='POSTED' AND l.account_id=? AND e.entry_date < ?
  ");
  $si->execute([$accountId,$from]);
  $saldoInicial = (float)$si->fetch()['v'];

  $st = $pdo->prepare("
    SELECT e.id entry_id, e.entry_date, e.description, l.memo, l.debit, l.credit
    FROM journal_lines l
    JOIN journal_entries e ON e.id=l.entry_id
    WHERE e.status='POSTED' AND l.account_id=? AND e.entry_date BETWEEN ? AND ?
    ORDER BY e.entry_date ASC, e.id ASC, l.line_no ASC
  ");
  $st->execute([$accountId,$from,$to]);
  $movs = $st->fetchAll();
}

require __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Libro mayor</h2>

  <form class="card" method="get">
    <div class="row">
      <div class="field" style="flex:1">
        <label>Cuenta</label>
        <select name="account_id">
          <option value="0">Selecciona...</option>
          <?php foreach ($accounts as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= $accountId===(int)$a['id']?'selected':'' ?>>
              <?= h($a['code']) ?> — <?= h($a['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
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
        <button class="btn">Ver</button>
      </div>
    </div>
  </form>

  <?php if ($acc): ?>
    <div class="small">Cuenta: <b><?= h($acc['code']) ?> — <?= h($acc['name']) ?></b></div>
    <div class="small">Saldo inicial: <b>$<?= number_format($saldoInicial,2,',','.') ?></b></div>

    <table class="table" style="margin-top:10px">
      <thead><tr><th>Fecha</th><th>Asiento</th><th>Glosa</th><th>Memo</th><th class="right">Debe</th><th class="right">Haber</th><th class="right">Saldo</th></tr></thead>
      <tbody>
        <?php $saldo = $saldoInicial; ?>
        <?php foreach ($movs as $m): ?>
          <?php $saldo += ((float)$m['debit'] - (float)$m['credit']); ?>
          <tr>
            <td><?= h($m['entry_date']) ?></td>
            <td><a href="entry_view.php?id=<?= (int)$m['entry_id'] ?>">#<?= (int)$m['entry_id'] ?></a></td>
            <td><?= h($m['description']) ?></td>
            <td><?= h($m['memo'] ?? '') ?></td>
            <td class="right">$<?= number_format((float)$m['debit'],2,',','.') ?></td>
            <td class="right">$<?= number_format((float)$m['credit'],2,',','.') ?></td>
            <td class="right">$<?= number_format($saldo,2,',','.') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="small">Selecciona una cuenta para ver su mayor.</div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
