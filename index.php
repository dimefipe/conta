<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();
require_company_selected();

$pdo = db();
$cid = current_company_id();

// rango por defecto mes actual
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-t');

// Ingresos del periodo
$stInc = $pdo->prepare("
  SELECT COALESCE(SUM(l.credit - l.debit),0) v
  FROM journal_lines l
  JOIN journal_entries e ON e.id=l.entry_id
  JOIN accounts a ON a.id=l.account_id
  WHERE e.status='POSTED'
    AND e.company_id=?
    AND e.entry_date BETWEEN ? AND ?
    AND a.company_id=?
    AND a.type='INCOME'
");
$stInc->execute([$cid,$from,$to,$cid]);
$income = (float)$stInc->fetch()['v'];

// Costos + Gastos del periodo
$stCx = $pdo->prepare("
  SELECT COALESCE(SUM(l.debit - l.credit),0) v
  FROM journal_lines l
  JOIN journal_entries e ON e.id=l.entry_id
  JOIN accounts a ON a.id=l.account_id
  WHERE e.status='POSTED'
    AND e.company_id=?
    AND e.entry_date BETWEEN ? AND ?
    AND a.company_id=?
    AND a.type IN ('COST','EXPENSE')
");
$stCx->execute([$cid,$from,$to,$cid]);
$costexp = (float)$stCx->fetch()['v'];

$net = $income - $costexp;

// Total asientos del periodo
$stCnt = $pdo->prepare("
  SELECT COUNT(*) c
  FROM journal_entries
  WHERE company_id=? AND entry_date BETWEEN ? AND ?
");
$stCnt->execute([$cid,$from,$to]);
$countEntries = (int)$stCnt->fetch()['c'];

// Últimos 10 asientos
$stLast = $pdo->prepare("
  SELECT e.*,
    (SELECT COALESCE(SUM(l.debit),0)  FROM journal_lines l WHERE l.entry_id=e.id) AS d,
    (SELECT COALESCE(SUM(l.credit),0) FROM journal_lines l WHERE l.entry_id=e.id) AS c
  FROM journal_entries e
  WHERE e.company_id=?
  ORDER BY e.entry_date DESC, e.id DESC
  LIMIT 10
");
$stLast->execute([$cid]);
$last = $stLast->fetchAll();

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <h2>Dashboard</h2>

  <form class="card" method="get">
    <div class="row">
      <div class="field">
        <label>Desde</label>
        <input type="date" name="from" value="<?= h($from) ?>">
      </div>
      <div class="field">
        <label>Hasta</label>
        <input type="date" name="to" value="<?= h($to) ?>">
      </div>
      <div class="field" style="align-self:flex-end">
        <button class="btn" type="submit">Actualizar</button>
      </div>

      <div class="field" style="align-self:flex-end; margin-left:auto">
        <a class="btn" href="entry_new.php">+ Nuevo asiento</a>
      </div>
    </div>
  </form>

  <div class="row">
    <div class="card" style="flex:1; min-width:260px">
      <div class="small">Ingresos (periodo)</div>
      <div style="font-size:26px; font-weight:800; margin-top:6px"><?= clp($income) ?></div>
    </div>

    <div class="card" style="flex:1; min-width:260px">
      <div class="small">Costos + Gastos (periodo)</div>
      <div style="font-size:26px; font-weight:800; margin-top:6px"><?= clp($costexp) ?></div>
    </div>

    <div class="card" style="flex:1; min-width:260px">
      <div class="small">Resultado (periodo)</div>
      <div style="font-size:26px; font-weight:800; margin-top:6px"><?= clp($net) ?></div>
      <div class="small">Asientos: <b><?= (int)$countEntries ?></b></div>
    </div>
  </div>
</div>

<div class="card">
  <h3>Últimos asientos</h3>
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Fecha</th>
        <th>Glosa</th>
        <th>Estado</th>
        <th class="right">Debe</th>
        <th class="right">Haber</th>
        <th>Ver</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$last): ?>
        <tr><td colspan="7" class="small">Aún no hay asientos.</td></tr>
      <?php else: ?>
        <?php foreach ($last as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= h($r['entry_date']) ?></td>
            <td><?= h($r['description']) ?></td>
            <td><?= h($r['status']) ?></td>
            <td class="right"><?= clp($r['d']) ?></td>
            <td class="right"><?= clp($r['c']) ?></td>
            <td><a class="btn secondary smallbtn" href="entry_view.php?id=<?= (int)$r['id'] ?>">Ver</a></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
