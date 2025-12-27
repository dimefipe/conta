<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();
require_company_selected();

$pdo = db();
$cid = current_company_id();

/**
 * Helpers locales por si no existen en tu helpers.php
 */
if (!function_exists('date_range_from_shortcut')) {
  function date_range_from_shortcut(string $p): array {
    $today = new DateTime('today');
    if ($p === 'week') {
      $monday = (clone $today)->modify('monday this week');
      $sunday = (clone $monday)->modify('+6 days');
      return [$monday->format('Y-m-d'), $sunday->format('Y-m-d')];
    }
    $from = (new DateTime('first day of this month'))->format('Y-m-d');
    $to   = (new DateTime('last day of this month'))->format('Y-m-d');
    return [$from, $to];
  }
}

// Cuentas de la empresa activa
$accSt = $pdo->prepare("SELECT id, code, name FROM accounts WHERE company_id=? AND is_active=1 ORDER BY code");
$accSt->execute([$cid]);
$accounts = $accSt->fetchAll();

$accountId = (int)($_GET['account_id'] ?? 0);
$shortcut  = $_GET['p'] ?? '';
$ym        = $_GET['ym'] ?? '';

if ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) {
  $from = $ym . '-01';
  $to   = date('Y-m-t', strtotime($from));
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

$movs = [];
$acc = null;
$saldoInicial = 0.0;

if ($accountId > 0) {
  // validar que la cuenta sea de la empresa activa
  $a = $pdo->prepare("SELECT * FROM accounts WHERE id=? AND company_id=?");
  $a->execute([$accountId, $cid]);
  $acc = $a->fetch();

  if ($acc) {
    // saldo inicial acumulado antes del periodo
    $si = $pdo->prepare("
      SELECT COALESCE(SUM(l.debit - l.credit),0) v
      FROM journal_lines l
      JOIN journal_entries e ON e.id=l.entry_id
      WHERE e.status='POSTED'
        AND e.company_id=?
        AND l.account_id=?
        AND e.entry_date < ?
    ");
    $si->execute([$cid, $accountId, $from]);
    $saldoInicial = (float)$si->fetch()['v'];

    // movimientos del periodo
    $st = $pdo->prepare("
      SELECT
        e.id AS entry_id,
        e.entry_date,
        e.description,
        l.memo,
        l.debit,
        l.credit
      FROM journal_lines l
      JOIN journal_entries e ON e.id=l.entry_id
      WHERE e.status='POSTED'
        AND e.company_id=?
        AND l.account_id=?
        AND e.entry_date BETWEEN ? AND ?
      ORDER BY e.entry_date ASC, e.id ASC, l.line_no ASC
    ");
    $st->execute([$cid, $accountId, $from, $to]);
    $movs = $st->fetchAll();
  }
}

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <h2>Libro mayor</h2>

  <form class="card" method="get">
    <div class="row">
      <div class="field" style="flex:1">
        <label>Cuenta</label>
        <select name="account_id" required>
          <option value="0">Selecciona...</option>
          <?php foreach ($accounts as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= $accountId === (int)$a['id'] ? 'selected' : '' ?>>
              <?= h($a['code']) ?> — <?= h($a['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

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
        <button class="btn" type="submit">Ver</button>
      </div>

      <div class="field" style="align-self:flex-end">
        <a class="btn secondary" href="ledger.php?account_id=<?= (int)$accountId ?>&p=week">Esta semana</a>
      </div>

      <div class="field" style="align-self:flex-end">
        <a class="btn secondary" href="ledger.php?account_id=<?= (int)$accountId ?>&p=month">Este mes</a>
      </div>
    </div>
  </form>

  <?php if ($accountId <= 0): ?>
    <div class="small">Selecciona una cuenta para ver su mayor.</div>
  <?php elseif (!$acc): ?>
    <div class="small">La cuenta seleccionada no existe o no pertenece a la empresa activa.</div>
  <?php else: ?>
    <div class="small">
      Cuenta: <b><?= h($acc['code']) ?> — <?= h($acc['name']) ?></b><br>
      Periodo: <b><?= h($from) ?></b> a <b><?= h($to) ?></b><br>
      Saldo inicial (antes del periodo): <b><?= clp($saldoInicial) ?></b>
    </div>

    <table class="table" style="margin-top:10px">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Asiento</th>
          <th>Glosa</th>
          <th>Memo</th>
          <th class="right">Debe</th>
          <th class="right">Haber</th>
          <th class="right">Saldo</th>
        </tr>
      </thead>
      <tbody>
        <?php $saldo = $saldoInicial; ?>

        <?php if (!$movs): ?>
          <tr><td colspan="7" class="small">No hay movimientos en este periodo.</td></tr>
        <?php else: ?>
          <?php foreach ($movs as $m): ?>
            <?php $saldo += ((float)$m['debit'] - (float)$m['credit']); ?>
            <tr>
              <td><?= h($m['entry_date']) ?></td>
              <td><a href="entry_view.php?id=<?= (int)$m['entry_id'] ?>">#<?= (int)$m['entry_id'] ?></a></td>
              <td><?= h($m['description']) ?></td>
              <td><?= h($m['memo'] ?? '') ?></td>
              <td class="right"><?= clp($m['debit']) ?></td>
              <td class="right"><?= clp($m['credit']) ?></td>
              <td class="right"><?= clp($saldo) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
