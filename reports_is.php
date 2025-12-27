<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();
require_company_selected();

$pdo = db();
$cid = current_company_id();

/**
 * Helpers locales
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

$shortcut = $_GET['p'] ?? '';
$ym = $_GET['ym'] ?? '';

if ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) {
  $from = $ym . '-01';
  $to   = date('Y-m-t', strtotime($from));
} elseif (in_array($shortcut, ['week','month'], true)) {
  [$from, $to] = date_range_from_shortcut($shortcut);
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

/**
 * Para IS:
 * - INCOME: saldo natural es credit - debit
 * - COST/EXPENSE: saldo natural es debit - credit
 */
function fetch_group(PDO $pdo, int $cid, string $type, string $from, string $to, bool $creditMinusDebit): array {
  $st = $pdo->prepare("
    SELECT
      a.id, a.code, a.name,
      COALESCE(SUM(l.debit),0)  AS deb,
      COALESCE(SUM(l.credit),0) AS cre
    FROM accounts a
    LEFT JOIN journal_lines l ON l.account_id=a.id
    LEFT JOIN journal_entries e ON e.id=l.entry_id
      AND e.status='POSTED'
      AND e.company_id=?
      AND e.entry_date BETWEEN ? AND ?
    WHERE a.company_id=?
      AND a.type=?
      AND a.is_active=1
    GROUP BY a.id, a.code, a.name
    ORDER BY a.code
  ");
  $st->execute([$cid,$from,$to,$cid,$type]);
  $rows = $st->fetchAll();

  $out = [];
  $total = 0.0;
  foreach ($rows as $r) {
    $deb = (float)$r['deb'];
    $cre = (float)$r['cre'];
    $val = $creditMinusDebit ? ($cre - $deb) : ($deb - $cre);
    // mostrar solo si hay movimiento o si quieres mostrar todo
    if (abs($val) < 0.00001) continue;

    $out[] = [
      'code'=>$r['code'],
      'name'=>$r['name'],
      'value'=>$val
    ];
    $total += $val;
  }
  return [$out, $total];
}

[$incomeRows, $totalIncome]   = fetch_group($pdo,$cid,'INCOME',$from,$to,true);
[$costRows, $totalCost]       = fetch_group($pdo,$cid,'COST',$from,$to,false);
[$expenseRows, $totalExpense] = fetch_group($pdo,$cid,'EXPENSE',$from,$to,false);

$gross = $totalIncome - $totalCost;
$net   = $gross - $totalExpense;

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <h2>Estado de Resultados (EE.RR.)</h2>

  <form class="card" method="get">
    <div class="row">
      <div class="field">
        <label>Mes/Año rápido</label>
        <select name="ym" onchange="this.form.submit()">
          <option value="">— Elegir —</option>
          <?php foreach ($years as $y): ?>
            <?php foreach ($months as $m => $label): $val = $y.'-'.$m; ?>
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
        <input type="date" name="from" value="<?= h($from) ?>" />
      </div>

      <div class="field">
        <label>Hasta</label>
        <input type="date" name="to" value="<?= h($to) ?>" />
      </div>

      <div class="field" style="align-self:flex-end">
        <button class="btn" type="submit">Filtrar</button>
      </div>

      <div class="field" style="align-self:flex-end">
        <a class="btn secondary" href="reports_is.php?p=week">Esta semana</a>
      </div>
      <div class="field" style="align-self:flex-end">
        <a class="btn secondary" href="reports_is.php?p=month">Este mes</a>
      </div>
    </div>
  </form>

  <div class="small">
    Periodo: <b><?= h($from) ?></b> a <b><?= h($to) ?></b>
  </div>

  <div class="grid-2" style="margin-top:12px">
    <div class="card">
      <h3>Ingresos</h3>
      <table class="table">
        <thead><tr><th>Código</th><th>Cuenta</th><th class="right">Monto</th></tr></thead>
        <tbody>
        <?php if (!$incomeRows): ?>
          <tr><td colspan="3" class="small">Sin ingresos en el periodo.</td></tr>
        <?php else: ?>
          <?php foreach ($incomeRows as $r): ?>
            <tr>
              <td><?= h($r['code']) ?></td>
              <td><?= h($r['name']) ?></td>
              <td class="right"><?= clp($r['value']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" class="right">Total ingresos</th>
            <th class="right"><?= clp($totalIncome) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="card">
      <h3>Costos</h3>
      <table class="table">
        <thead><tr><th>Código</th><th>Cuenta</th><th class="right">Monto</th></tr></thead>
        <tbody>
        <?php if (!$costRows): ?>
          <tr><td colspan="3" class="small">Sin costos en el periodo.</td></tr>
        <?php else: ?>
          <?php foreach ($costRows as $r): ?>
            <tr>
              <td><?= h($r['code']) ?></td>
              <td><?= h($r['name']) ?></td>
              <td class="right"><?= clp($r['value']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" class="right">Total costos</th>
            <th class="right"><?= clp($totalCost) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div class="card" style="margin-top:12px">
    <h3>Gastos</h3>
    <table class="table">
      <thead><tr><th>Código</th><th>Cuenta</th><th class="right">Monto</th></tr></thead>
      <tbody>
      <?php if (!$expenseRows): ?>
        <tr><td colspan="3" class="small">Sin gastos en el periodo.</td></tr>
      <?php else: ?>
        <?php foreach ($expenseRows as $r): ?>
          <tr>
            <td><?= h($r['code']) ?></td>
            <td><?= h($r['name']) ?></td>
            <td class="right"><?= clp($r['value']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="2" class="right">Total gastos</th>
          <th class="right"><?= clp($totalExpense) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="card" style="margin-top:12px">
    <table class="table">
      <tbody>
        <tr>
          <th class="right">Utilidad bruta (Ingresos - Costos)</th>
          <th class="right" style="width:220px"><?= clp($gross) ?></th>
        </tr>
        <tr>
          <th class="right">Resultado del periodo (Bruta - Gastos)</th>
          <th class="right"><?= clp($net) ?></th>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
