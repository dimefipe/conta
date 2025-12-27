<?php
require_once __DIR__ . '/lib/helpers.php';

require_login();
require_company_selected();

$pdo = db();
$cid = current_company_id();

/**
 * -------------------------
 * Filtros de fecha (centralizados en helpers.php)
 * - Preferido: ym=YYYY-MM
 * - Compat: m=1..12 & y=YYYY
 * - Atajos: range=week|month|last_month|last_3_months
 * - Manual: from=Y-m-d & to=Y-m-d
 * -------------------------
 */
$shortcut = get_str('range'); // para pintar select

// Compat: si llega m/y => lo convertimos a ym (sin redirigir)
$m = (int)($_GET['m'] ?? 0);
$y = (int)($_GET['y'] ?? 0);
if ($m >= 1 && $m <= 12 && $y >= 2000 && $y <= 2100) {
  $_GET['ym'] = sprintf('%04d-%02d', $y, $m);
  $shortcut = ''; // si eligió mes/año, no mostramos rango activo
}

// Resolver usando helper
$filters = resolve_date_filters();
$from = $filters['from'];
$to   = $filters['to'];
$ym   = $filters['ym'];

// Default duro: mes actual
if ($from === '' || $to === '') {
  $ym = date('Y-m');
  [$from, $to] = ym_range($ym);
}

// Para select Mes/Año (si hay ym válido)
$selYear  = (is_valid_ym($ym)) ? (int)substr($ym, 0, 4) : (int)date('Y');
$selMonth = (is_valid_ym($ym)) ? (int)substr($ym, 5, 2) : (int)date('n');

/**
 * -------------------------
 * Años disponibles (según data real)
 * -------------------------
 */
$stMin = $pdo->prepare("SELECT MIN(entry_date) FROM journal_entries WHERE company_id=?");
$stMax = $pdo->prepare("SELECT MAX(entry_date) FROM journal_entries WHERE company_id=?");
$stMin->execute([$cid]);
$stMax->execute([$cid]);

$minDate = $stMin->fetchColumn();
$maxDate = $stMax->fetchColumn();

$defaultYear = (int)date('Y');
$minYear = $minDate ? (int)substr((string)$minDate, 0, 4) : $defaultYear;
$maxYear = $maxDate ? (int)substr((string)$maxDate, 0, 4) : $defaultYear;

if ($minYear > $maxYear) { $tmp = $minYear; $minYear = $maxYear; $maxYear = $tmp; }

$years = range($maxYear, $minYear);
if (!$years) $years = [$defaultYear];

/**
 * -------------------------
 * Consultas KPI (solo POSTED)
 * -------------------------
 */
function sum_by_types_period(PDO $pdo, int $cid, array $types, string $from, string $to): float {
  $in = implode(',', array_fill(0, count($types), '?'));

  $sql = "
    SELECT a.type,
           COALESCE(SUM(jl.debit),0)  AS d,
           COALESCE(SUM(jl.credit),0) AS c
    FROM journal_lines jl
    JOIN journal_entries je ON je.id = jl.entry_id
    JOIN accounts a ON a.id = jl.account_id
    WHERE je.company_id = ?
      AND je.status = 'POSTED'
      AND je.entry_date BETWEEN ? AND ?
      AND a.company_id = ?
      AND a.type IN ($in)
    GROUP BY a.type
  ";

  $params = array_merge([$cid, $from, $to, $cid], $types);
  $st = $pdo->prepare($sql);
  $st->execute($params);

  $total = 0.0;
  while ($r = $st->fetch()) {
    $type = $r['type'];
    $d = (float)$r['d'];
    $c = (float)$r['c'];
    if (in_array($type, ['LIABILITY','EQUITY','INCOME'], true)) {
      $total += ($c - $d);
    } else {
      $total += ($d - $c);
    }
  }
  return $total;
}

function sum_balance_asof(PDO $pdo, int $cid, string $to): array {
  $sql = "
    SELECT a.type,
           COALESCE(SUM(jl.debit),0)  AS d,
           COALESCE(SUM(jl.credit),0) AS c
    FROM journal_lines jl
    JOIN journal_entries je ON je.id = jl.entry_id
    JOIN accounts a ON a.id = jl.account_id
    WHERE je.company_id = ?
      AND je.status = 'POSTED'
      AND je.entry_date <= ?
      AND a.company_id = ?
      AND a.type IN ('ASSET','LIABILITY','EQUITY')
    GROUP BY a.type
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$cid, $to, $cid]);

  $assets = 0.0; $liab = 0.0; $eq = 0.0;
  while ($r = $st->fetch()) {
    $type = $r['type'];
    $d = (float)$r['d'];
    $c = (float)$r['c'];
    if ($type === 'ASSET') $assets = ($d - $c);
    if ($type === 'LIABILITY') $liab = ($c - $d);
    if ($type === 'EQUITY') $eq = ($c - $d);
  }
  return [$assets, $liab, $eq];
}

$ingresos  = sum_by_types_period($pdo, $cid, ['INCOME'],  $from, $to);
$costos    = sum_by_types_period($pdo, $cid, ['COST'],    $from, $to);
$gastos    = sum_by_types_period($pdo, $cid, ['EXPENSE'], $from, $to);
$resultado = $ingresos - $costos - $gastos;

[$bsAssets, $bsLiab, $bsEq] = sum_balance_asof($pdo, $cid, $to);

/**
 * -------------------------
 * Últimos asientos del periodo
 * -------------------------
 */
$stEntries = $pdo->prepare("
  SELECT id, entry_date, description
  FROM journal_entries
  WHERE company_id = ?
    AND status = 'POSTED'
    AND entry_date BETWEEN ? AND ?
  ORDER BY entry_date DESC, id DESC
  LIMIT 10
");
$stEntries->execute([$cid, $from, $to]);
$entries = $stEntries->fetchAll();

$months = [
  1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
  7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
];

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <h2>Dashboard</h2>
  <div class="small">Periodo: <b><?= h($from) ?></b> a <b><?= h($to) ?></b></div>

  <!-- Filtros -->
  <form method="get" class="card" style="margin-top:12px">
    <h3 style="margin-top:0">Filtros rápidos</h3>

    <!-- Botones rápidos -->
    <div class="row" style="gap:8px; flex-wrap:wrap; margin-bottom:10px">
      <a class="btn secondary smallbtn" href="index.php?range=month">Mes actual</a>
      <a class="btn secondary smallbtn" href="index.php?range=last_month">Mes anterior</a>
      <a class="btn secondary smallbtn" href="index.php?range=last_3_months">Últimos 3 meses</a>
      <a class="btn secondary smallbtn" href="index.php?range=week">Semana actual</a>
      <a class="btn secondary smallbtn" href="index.php">Reset</a>
    </div>

    <div class="row">
      <!-- Mes / Año -->
      <div class="field" style="min-width:220px">
        <label>Mes</label>
        <select name="m">
          <?php foreach ($months as $k=>$label): ?>
            <option value="<?= (int)$k ?>" <?= ($k === (int)$selMonth) ? 'selected' : '' ?>>
              <?= h($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field" style="min-width:180px">
        <label>Año</label>
        <select name="y">
          <?php foreach ($years as $yy): ?>
            <option value="<?= (int)$yy ?>" <?= ((int)$yy === (int)$selYear) ? 'selected' : '' ?>>
              <?= (int)$yy ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field" style="align-self:flex-end">
        <button class="btn">Aplicar Mes/Año</button>
      </div>
    </div>

    <div class="row" style="margin-top:10px; align-items:flex-end">
      <!-- Atajos -->
      <div class="field" style="min-width:260px">
        <label>Atajos</label>
        <select name="range">
          <option value="">—</option>
          <option value="week" <?= ($shortcut==='week')?'selected':'' ?>>Semana actual</option>
          <option value="month" <?= ($shortcut==='month')?'selected':'' ?>>Mes actual</option>
          <option value="last_month" <?= ($shortcut==='last_month')?'selected':'' ?>>Mes anterior</option>
          <option value="last_3_months" <?= ($shortcut==='last_3_months')?'selected':'' ?>>Últimos 3 meses</option>
        </select>
      </div>

      <!-- Manual -->
      <div class="field" style="min-width:200px">
        <label>Desde</label>
        <input type="date" name="from" value="<?= h($from) ?>">
      </div>

      <div class="field" style="min-width:200px">
        <label>Hasta</label>
        <input type="date" name="to" value="<?= h($to) ?>">
      </div>

      <div class="field">
        <button class="btn secondary" type="submit">Aplicar</button>
      </div>
    </div>

    <div class="small" style="margin-top:8px">
      Tip: si eliges <b>Mes/Año</b>, automáticamente toma del <b>01</b> al <b>último día</b>.
    </div>
  </form>

  <!-- KPIs -->
  <div class="row" style="margin-top:12px">
    <div class="card" style="flex:1; min-width:220px">
      <div class="small">Ingresos (periodo)</div>
      <div style="font-size:22px; font-weight:800"><?= h(clp($ingresos)) ?></div>
    </div>
    <div class="card" style="flex:1; min-width:220px">
      <div class="small">Costos (periodo)</div>
      <div style="font-size:22px; font-weight:800"><?= h(clp($costos)) ?></div>
    </div>
    <div class="card" style="flex:1; min-width:220px">
      <div class="small">Gastos (periodo)</div>
      <div style="font-size:22px; font-weight:800"><?= h(clp($gastos)) ?></div>
    </div>
    <div class="card" style="flex:1; min-width:220px">
      <div class="small">Resultado (periodo)</div>
      <div style="font-size:22px; font-weight:800"><?= h(clp($resultado)) ?></div>
    </div>
  </div>

  <!-- Balance as-of -->
  <div class="row" style="margin-top:12px">
    <div class="card" style="flex:1; min-width:220px">
      <div class="small">Activos (a <?= h($to) ?>)</div>
      <div style="font-size:20px; font-weight:800"><?= h(clp($bsAssets)) ?></div>
    </div>
    <div class="card" style="flex:1; min-width:220px">
      <div class="small">Pasivos (a <?= h($to) ?>)</div>
      <div style="font-size:20px; font-weight:800"><?= h(clp($bsLiab)) ?></div>
    </div>
    <div class="card" style="flex:1; min-width:220px">
      <div class="small">Patrimonio (a <?= h($to) ?>)</div>
      <div style="font-size:20px; font-weight:800"><?= h(clp($bsEq)) ?></div>
    </div>
  </div>

  <!-- Últimos asientos -->
  <div class="card" style="margin-top:12px">
    <h3 style="margin-top:0">Últimos asientos del periodo</h3>

    <table class="table">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Descripción</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$entries): ?>
          <tr><td colspan="3" class="small">No hay asientos POSTED en este periodo.</td></tr>
        <?php else: ?>
          <?php foreach ($entries as $e): ?>
            <tr>
              <td><?= h($e['entry_date']) ?></td>
              <td><?= h($e['description']) ?></td>
              <td>
                <a class="btn secondary smallbtn" href="entry_view.php?id=<?= (int)$e['id'] ?>">Ver</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div style="margin-top:10px">
      <a class="btn" href="entry_new.php">+ Nuevo asiento</a>
      <a class="btn secondary" href="entries.php">Ir a Libro Diario</a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
