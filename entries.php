<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();

$pdo = db();

/**
 * Filtros:
 * - ym=YYYY-MM (prioridad)
 * - p=week|month
 * - from/to manual
 */
$shortcut = $_GET['p'] ?? '';
$ym = $_GET['ym'] ?? '';

if ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) {
  $from = $ym . '-01';
  $to = date('Y-m-t', strtotime($from));
} elseif (in_array($shortcut, ['week','month'], true)) {
  [$from, $to] = date_range_from_shortcut($shortcut);
} else {
  $from = $_GET['from'] ?? date('Y-m-01');
  $to   = $_GET['to']   ?? date('Y-m-t');
}

// Data
$st = $pdo->prepare("
  SELECT e.*,
    (SELECT COALESCE(SUM(l.debit),0) FROM journal_lines l WHERE l.entry_id=e.id) debit_total,
    (SELECT COALESCE(SUM(l.credit),0) FROM journal_lines l WHERE l.entry_id=e.id) credit_total
  FROM journal_entries e
  WHERE e.entry_date BETWEEN ? AND ?
  ORDER BY e.entry_date DESC, e.id DESC
");
$st->execute([$from, $to]);
$rows = $st->fetchAll();

/**
 * UI helpers para selector Mes/Año
 * - lista dinámica: últimos 3 años, año actual, +1
 */
$years = range((int)date('Y') - 3, (int)date('Y') + 1);
$months = [
  '01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
  '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'
];

// Para que el selector marque el mes actual filtrado
$currentYM = substr($from, 0, 7);

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <h2>Libro diario</h2>

  <form class="card" method="get">
    <div class="row">

      <!-- Selector rápido Mes/Año -->
      <div class="field">
        <label>Mes/Año rápido</label>
        <select name="ym" onchange="this.form.submit()">
          <option value="">— Elegir —</option>
          <?php foreach ($years as $y): ?>
            <?php foreach ($months as $m => $label): $val = $y . '-' . $m; ?>
              <option value="<?= h($val) ?>" <?= ($currentYM === $val) ? 'selected' : '' ?>>
                <?= h($label) ?> <?= h((string)$y) ?>
              </option>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </select>
        <div class="small">Al elegir, filtra del 01 al último día del mes.</div>
      </div>

      <!-- Filtro manual -->
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

      <!-- Atajos -->
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

  <div class="small">Mostrando movimientos desde <b><?= h($from) ?></b> hasta <b><?= h($to) ?></b>.</div>

  <table class="table" style="margin-top:10px">
    <thead>
      <tr>
        <th>#</th>
        <th>Fecha</th>
        <th>Glosa</th>
        <th>Estado</th>
        <th class="right">Debe</th>
        <th class="right">Haber</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr>
          <td colspan="7" class="small">No hay asientos en este periodo.</td>
        </tr>
      <?php endif; ?>

      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h($r['entry_date']) ?></td>
          <td><?= h($r['description']) ?></td>
          <td><?= h($r['status']) ?></td>
          <td class="right">$<?= number_format((float)$r['debit_total'], 2, ',', '.') ?></td>
          <td class="right">$<?= number_format((float)$r['credit_total'], 2, ',', '.') ?></td>
          <td>
            <a class="btn secondary" href="entry_view.php?id=<?= (int)$r['id'] ?>">Ver</a>

            <?php if ($r['status'] === 'POSTED'): ?>
              <a
                class="btn danger"
                href="entry_void.php?id=<?= (int)$r['id'] ?>&csrf=<?= h(csrf_token()) ?>"
                onclick="return confirm('¿Anular asiento #<?= (int)$r['id'] ?>?');"
              >Anular</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
