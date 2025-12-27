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
 * Balance: saldos acumulados HASTA "to" (incluido)
 * - Activos: debit-credit
 * - Pasivos/Patrimonio: credit-debit (lo mostramos positivo)
 */
function fetch_bs_group(PDO $pdo, int $cid, string $type, string $to, bool $creditMinusDebit): array {
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
      AND e.entry_date <= ?
    WHERE a.company_id=?
      AND a.type=?
      AND a.is_active=1
    GROUP BY a.id, a.code, a.name
    ORDER BY a.code
  ");
  $st->execute([$cid,$to,$cid,$type]);
  $rows = $st->fetchAll();

  $out = [];
  $total = 0.0;
  foreach ($rows as $r) {
    $deb = (float)$r['deb'];
    $cre = (float)$r['cre'];
    $val = $creditMinusDebit ? ($cre - $deb) : ($deb - $cre);
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

// Activos
[$assetRows, $totalAssets] = fetch_bs_group($pdo,$cid,'ASSET',$to,false);

// Pasivos y Patrimonio (mostrar positivos)
[$liabRowsRaw, $totalLiabRaw] = fetch_bs_group($pdo,$cid,'LIABILITY',$to,true);
[$eqRowsRaw,   $totalEqRaw]   = fetch_bs_group($pdo,$cid,'EQUITY',$to,true);

$totalLiab = $totalLiabRaw; // ya viene credit-debit (positivo típico)
$totalEq   = $totalEqRaw;

// Resultado del periodo (IS) para presentar como “Resultado del periodo”
function net_income(PDO $pdo, int $cid, string $from, string $to): float {
  // INCOME (credit-debit)
  $inc = $pdo->prepare("
    SELECT COALESCE(SUM(l.credit - l.debit),0) v
    FROM journal_lines l
    JOIN journal_entries e ON e.id=l.entry_id
    JOIN accounts a ON a.id=l.account_id
    WHERE e.status='POSTED' AND e.company_id=?
      AND e.entry_date BETWEEN ? AND ?
      AND a.company_id=? AND a.type='INCOME'
  ");
  $inc->execute([$cid,$from,$to,$cid]);
  $income = (float)$inc->fetch()['v'];

  // COST/EXPENSE (debit-credit)
  $cx = $pdo->prepare("
    SELECT COALESCE(SUM(l.debit - l.credit),0) v
    FROM journal_lines l
    JOIN journal_entries e ON e.id=l.entry_id
    JOIN accounts a ON a.id=l.account_id
    WHERE e.status='POSTED' AND e.company_id=?
      AND e.entry_date BETWEEN ? AND ?
      AND a.company_id=? AND a.type IN ('COST','EXPENSE')
  ");
  $cx->execute([$cid,$from,$to,$cid]);
  $costexp = (float)$cx->fetch()['v'];

  return $income - $costexp;
}

$net = net_income($pdo,$cid,$from,$to);

$liabPlusEq = $totalLiab + $totalEq;
$liabPlusEqPlusNet = $liabPlusEq + $net;

// Diferencia para chequeo rápido (no siempre cuadra en MVP si falta asiento de cierre)
$diff = $totalAssets - $liabPlusEqPlusNet;

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <h2>Balance</h2>

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
        <div class="small">Filtra el reporte por periodo (para resultado del periodo) y acumula balance hasta “Hasta”.</div>
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
        <a class="btn secondary" href="reports_bs.php?p=week">Esta semana</a>
      </div>
      <div class="field" style="align-self:flex-end">
        <a class="btn secondary" href="reports_bs.php?p=month">Este mes</a>
      </div>
    </div>
  </form>

  <div class="small">
    Periodo (Resultado): <b><?= h($from) ?></b> a <b><?= h($to) ?></b><br>
    Balance acumulado hasta: <b><?= h($to) ?></b>
  </div>

  <div class="grid-2" style="margin-top:12px">
    <div class="card">
      <h3>Activos</h3>
      <table class="table">
        <thead><tr><th>Código</th><th>Cuenta</th><th class="right">Saldo</th></tr></thead>
        <tbody>
          <?php if (!$assetRows): ?>
            <tr><td colspan="3" class="small">Sin activos con saldo.</td></tr>
          <?php else: ?>
            <?php foreach ($assetRows as $r): ?>
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
            <th colspan="2" class="right">Total activos</th>
            <th class="right"><?= clp($totalAssets) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="card">
      <h3>Pasivos</h3>
      <table class="table">
        <thead><tr><th>Código</th><th>Cuenta</th><th class="right">Saldo</th></tr></thead>
        <tbody>
          <?php if (!$liabRowsRaw): ?>
            <tr><td colspan="3" class="small">Sin pasivos con saldo.</td></tr>
          <?php else: ?>
            <?php foreach ($liabRowsRaw as $r): ?>
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
            <th colspan="2" class="right">Total pasivos</th>
            <th class="right"><?= clp($totalLiab) ?></th>
          </tr>
        </tfoot>
      </table>

      <h3 style="margin-top:14px">Patrimonio</h3>
      <table class="table">
        <thead><tr><th>Código</th><th>Cuenta</th><th class="right">Saldo</th></tr></thead>
        <tbody>
          <?php if (!$eqRowsRaw): ?>
            <tr><td colspan="3" class="small">Sin patrimonio con saldo.</td></tr>
          <?php else: ?>
            <?php foreach ($eqRowsRaw as $r): ?>
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
            <th colspan="2" class="right">Total patrimonio</th>
            <th class="right"><?= clp($totalEq) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div class="card" style="margin-top:12px">
    <table class="table">
      <tbody>
        <tr>
          <th class="right">Resultado del periodo (EE.RR.)</th>
          <th class="right" style="width:220px"><?= clp($net) ?></th>
        </tr>
        <tr>
          <th class="right">Pasivo + Patrimonio</th>
          <th class="right"><?= clp($liabPlusEq) ?></th>
        </tr>
        <tr>
          <th class="right">Pasivo + Patrimonio + Resultado</th>
          <th class="right"><?= clp($liabPlusEqPlusNet) ?></th>
        </tr>
        <tr>
          <th class="right">Diferencia (Activos - (P+E+R))</th>
          <th class="right"><?= clp($diff) ?></th>
        </tr>
      </tbody>
    </table>
    <div class="small">
      Nota: en contabilidad formal, el balance cuadra perfecto tras asientos de cierre. En este MVP mostramos la diferencia como chequeo rápido.
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
