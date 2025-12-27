<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();
require_company_selected();

$pdo = db();
$cid = current_company_id();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('entries.php');

// asiento debe ser de empresa activa
$st = $pdo->prepare("SELECT * FROM journal_entries WHERE id=? AND company_id=?");
$st->execute([$id,$cid]);
$entry = $st->fetch();
if (!$entry) {
  flash_set('err','Asiento no existe o no pertenece a la empresa activa.');
  redirect('entries.php');
}

$lines = $pdo->prepare("
  SELECT l.*, a.code, a.name, a.type
  FROM journal_lines l
  JOIN accounts a ON a.id=l.account_id
  WHERE l.entry_id=?
  ORDER BY l.line_no
");
$lines->execute([$id]);
$rows = $lines->fetchAll();

$totalD = 0.0; $totalC = 0.0;
foreach ($rows as $r) { $totalD += (float)$r['debit']; $totalC += (float)$r['credit']; }
$ok = (abs($totalD - $totalC) <= 0.00001);

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px">
    <div>
      <h2>Asiento #<?= (int)$entry['id'] ?></h2>
      <div class="small">
        Fecha: <b><?= h($entry['entry_date']) ?></b> ·
        Estado: <b><?= h($entry['status']) ?></b>
        <?php if ($entry['status'] === 'VOID' && $entry['voided_at']): ?>
          · Anulado: <b><?= h($entry['voided_at']) ?></b>
        <?php endif; ?>
      </div>
    </div>

    <div style="display:flex; gap:10px">
      <a class="btn secondary" href="entries.php">Volver</a>

      <?php if ($entry['status'] === 'POSTED'): ?>
        <a class="btn danger"
           href="entry_void.php?id=<?= (int)$entry['id'] ?>&csrf=<?= h(csrf_token()) ?>"
           onclick="return confirm('¿Anular asiento #<?= (int)$entry['id'] ?>?');">
          Anular
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card" style="margin-top:12px">
    <h3>Glosa</h3>
    <div><?= h($entry['description']) ?></div>
  </div>

  <div class="card" style="margin-top:12px">
    <h3>Detalle</h3>

    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Cuenta</th>
          <th>Nombre</th>
          <th>Memo</th>
          <th class="right">Debe</th>
          <th class="right">Haber</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['line_no'] ?></td>
            <td><?= h($r['code']) ?></td>
            <td><?= h($r['name']) ?></td>
            <td><?= h($r['memo'] ?? '') ?></td>
            <td class="right"><?= clp($r['debit']) ?></td>
            <td class="right"><?= clp($r['credit']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="4" class="right">Totales</th>
          <th class="right"><?= clp($totalD) ?></th>
          <th class="right"><?= clp($totalC) ?></th>
        </tr>
      </tfoot>
    </table>

    <div class="small" style="margin-top:8px">
      <?php if ($ok): ?>
        ✅ Cuadra correctamente (Debe = Haber).
      <?php else: ?>
        ⚠️ No cuadra (revisar líneas).
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
