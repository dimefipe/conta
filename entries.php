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

$st = $pdo->prepare("
  SELECT e.*,
    (SELECT COALESCE(SUM(l.debit),0) FROM journal_lines l WHERE l.entry_id=e.id) debit_total,
    (SELECT COALESCE(SUM(l.credit),0) FROM journal_lines l WHERE l.entry_id=e.id) credit_total
  FROM journal_entries e
  WHERE e.entry_date BETWEEN ? AND ?
  ORDER BY e.entry_date DESC, e.id DESC
");
$st->execute([$from,$to]);
$rows = $st->fetchAll();

require __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Libro diario</h2>

  <form class="card" method="get">
    <div class="row">
      <div class="field">
        <label>Desde</label>
        <input type="date" name="from" value="<?= h($from) ?>" />
      </div>
      <div class="field">
        <label>Hasta</label>
        <input type="date" name="to" value="<?= h($to) ?>" />
      </div>
      <div class="field" style="align-self:flex-end">
        <button class="btn">Filtrar</button>
      </div>
      <div class="field" style="align-self:flex-end">
        <a class="btn secondary" href="entries.php?p=week">Esta semana</a>
      </div>
      <div class="field" style="align-self:flex-end">
        <a class="btn secondary" href="entries.php?p=month">Este mes</a>
      </div>
      <div class="field" style="align-self:flex-end; margin-left:auto">
        <a class="btn" href="entry_new.php">Crear asiento</a>
      </div>
    </div>
  </form>

  <table class="table">
    <thead>
      <tr>
        <th>#</th><th>Fecha</th><th>Glosa</th><th>Estado</th>
        <th class="right">Debe</th><th class="right">Haber</th><th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h($r['entry_date']) ?></td>
          <td><?= h($r['description']) ?></td>
          <td><?= h($r['status']) ?></td>
          <td class="right">$<?= number_format((float)$r['debit_total'],2,',','.') ?></td>
          <td class="right">$<?= number_format((float)$r['credit_total'],2,',','.') ?></td>
          <td>
            <a class="btn secondary" href="entry_view.php?id=<?= (int)$r['id'] ?>">Ver</a>
            <?php if ($r['status'] === 'POSTED'): ?>
              <a class="btn danger" href="entry_void.php?id=<?= (int)$r['id'] ?>&csrf=<?= h(csrf_token()) ?>"
                 onclick="return confirm('¿Anular asiento #<?= (int)$r['id'] ?>?');">Anular</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
