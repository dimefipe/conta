<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$e = $pdo->prepare("SELECT * FROM journal_entries WHERE id=?");
$e->execute([$id]);
$entry = $e->fetch();
if (!$entry) { http_response_code(404); die("No existe."); }

$lines = $pdo->prepare("
  SELECT l.*, a.code, a.name
  FROM journal_lines l
  JOIN accounts a ON a.id=l.account_id
  WHERE l.entry_id=?
  ORDER BY l.line_no
");
$lines->execute([$id]);
$rows = $lines->fetchAll();

$sumD = 0; $sumC = 0;
foreach ($rows as $r) { $sumD += (float)$r['debit']; $sumC += (float)$r['credit']; }

require __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Asiento #<?= (int)$entry['id'] ?></h2>
  <div class="small">Fecha: <b><?= h($entry['entry_date']) ?></b> | Estado: <b><?= h($entry['status']) ?></b></div>
  <p><?= h($entry['description']) ?></p>

  <table class="table">
    <thead><tr><th>#</th><th>Cuenta</th><th>Memo</th><th class="right">Debe</th><th class="right">Haber</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['line_no'] ?></td>
          <td><?= h($r['code']) ?> — <?= h($r['name']) ?></td>
          <td><?= h($r['memo'] ?? '') ?></td>
          <td class="right">$<?= number_format((float)$r['debit'],2,',','.') ?></td>
          <td class="right">$<?= number_format((float)$r['credit'],2,',','.') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="3" class="right">Totales</th>
        <th class="right">$<?= number_format($sumD,2,',','.') ?></th>
        <th class="right">$<?= number_format($sumC,2,',','.') ?></th>
      </tr>
    </tfoot>
  </table>

  <div style="margin-top:12px">
    <a class="btn secondary" href="entries.php">Volver</a>
    <?php if ($entry['status']==='POSTED'): ?>
      <a class="btn danger" href="entry_void.php?id=<?= (int)$entry['id'] ?>&csrf=<?= h(csrf_token()) ?>"
         onclick="return confirm('¿Anular asiento #<?= (int)$entry['id'] ?>?');">Anular</a>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
