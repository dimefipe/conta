<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();
require_company_selected();

$pdo = db();
$cid = (int) current_company_id();

// -----------------------
// Cuentas activas de la empresa
// -----------------------
$accSt = $pdo->prepare("SELECT id, code, name, type FROM accounts WHERE company_id=? AND is_active=1 ORDER BY code");
$accSt->execute([$cid]);
$accounts = $accSt->fetchAll();

// -----------------------
// Plantillas disponibles (para selector)
// -----------------------
$tplSt = $pdo->prepare("SELECT id, name FROM entry_templates WHERE company_id=? ORDER BY name ASC");
$tplSt->execute([$cid]);
$templates = $tplSt->fetchAll() ?: [];

// Template seleccionado (GET o POST para mantener selección)
$selectedTemplateId = (int)($_GET['template_id'] ?? $_POST['template_id'] ?? 0);
$template = null;
$templateLines = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $selectedTemplateId > 0) {
  // Cargar template solo en GET (en POST usamos lo que viene del form)
  $tSt = $pdo->prepare("SELECT * FROM entry_templates WHERE id=? AND company_id=?");
  $tSt->execute([$selectedTemplateId, $cid]);
  $template = $tSt->fetch();

  if ($template) {
    $tlSt = $pdo->prepare("
      SELECT l.*, a.code, a.name AS account_name
      FROM entry_template_lines l
      JOIN accounts a ON a.id = l.account_id
      WHERE l.template_id = ?
      ORDER BY l.sort_order ASC
    ");
    $tlSt->execute([$selectedTemplateId]);
    $templateLines = $tlSt->fetchAll() ?: [];
  } else {
    $selectedTemplateId = 0; // template no pertenece a la empresa
  }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');

  $date = $_POST['entry_date'] ?? '';
  $desc = trim($_POST['description'] ?? '');

  $account_ids = $_POST['account_id'] ?? [];
  $memos       = $_POST['memo'] ?? [];
  $debits      = $_POST['debit'] ?? [];
  $credits     = $_POST['credit'] ?? [];

  if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $errors[] = 'Fecha inválida.';
  }
  if ($desc === '') {
    $errors[] = 'Glosa requerida.';
  }

  $lines = [];
  $totalD = 0.0;
  $totalC = 0.0;

  // Normaliza y valida líneas
  for ($i=0; $i<count($account_ids); $i++) {
    $aid = (int)($account_ids[$i] ?? 0);
    $memo = trim($memos[$i] ?? '');

    $d = trim((string)($debits[$i] ?? ''));
    $c = trim((string)($credits[$i] ?? ''));

    // permite "1000", "1.000", "1,000" -> normalizar
    $d = str_replace(['.', ' '], ['', ''], $d);
    $c = str_replace(['.', ' '], ['', ''], $c);
    $d = str_replace([','], ['.'], $d);
    $c = str_replace([','], ['.'], $c);

    $debit  = ($d === '') ? 0.0 : (float)$d;
    $credit = ($c === '') ? 0.0 : (float)$c;

    if ($aid <= 0) continue;
    if ($debit < 0 || $credit < 0) {
      $errors[] = 'No se permiten montos negativos.';
      break;
    }
    if ($debit > 0 && $credit > 0) {
      $errors[] = 'Una línea no puede tener Debe y Haber a la vez.';
      break;
    }
    if ($debit == 0 && $credit == 0) continue;

    $lines[] = [
      'account_id' => $aid,
      'memo' => $memo,
      'debit' => $debit,
      'credit' => $credit,
    ];
    $totalD += $debit;
    $totalC += $credit;
  }

  if (count($lines) < 2) $errors[] = 'Debes ingresar al menos 2 líneas.';
  if (abs($totalD - $totalC) > 0.00001) $errors[] = 'Debe y Haber deben cuadrar.';

  // extra seguridad: validar que cuentas sean de la empresa
  if (!$errors && $lines) {
    $ids = array_map(fn($l)=>$l['account_id'], $lines);
    $ids = array_values(array_unique($ids));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $check = $pdo->prepare("SELECT COUNT(*) c FROM accounts WHERE company_id=? AND id IN ($placeholders)");
    $check->execute(array_merge([$cid], $ids));
    $countOk = (int)$check->fetch()['c'];
    if ($countOk !== count($ids)) {
      $errors[] = 'Hay cuentas que no pertenecen a la empresa activa.';
    }
  }

  if (!$errors) {
    $pdo->beginTransaction();
    try {
      // ✅ CAMBIO CLAVE: crear como DRAFT para permitir cargar líneas sin gatillar validación de POST
      $st = $pdo->prepare("INSERT INTO journal_entries(company_id, entry_date, description, status) VALUES(?,?,?, 'DRAFT')");
      $st->execute([$cid, $date, $desc]);
      $entryId = (int)$pdo->lastInsertId();

      $ins = $pdo->prepare("INSERT INTO journal_lines(entry_id, line_no, account_id, memo, debit, credit) VALUES(?,?,?,?,?,?)");
      $ln = 1;
      foreach ($lines as $l) {
        $ins->execute([$entryId, $ln, $l['account_id'], $l['memo'], $l['debit'], $l['credit']]);
        $ln++;
      }

      // ✅ CAMBIO CLAVE: ahora sí, "postear" con UPDATE (aquí corre trg_journal_entries_validate_posted)
      $up = $pdo->prepare("UPDATE journal_entries SET status='POSTED' WHERE id=? AND company_id=?");
      $up->execute([$entryId, $cid]);

      $pdo->commit();
      flash_set('ok', 'Asiento creado.');
      redirect('entry_view.php?id=' . $entryId);
    } catch (Throwable $e) {
      $pdo->rollBack();
      $errors[] = 'Error guardando: ' . $e->getMessage();
    }
  }
}

// -----------------------
// Helpers para precarga UI (POST tiene prioridad, luego template, luego vacío)
// -----------------------
$postedAccountIds = $_POST['account_id'] ?? null;
$usePosted = is_array($postedAccountIds);

$prefillDate = $_POST['entry_date'] ?? date('Y-m-d');

// Glosa: POST > template name (si hay) > vacío
$prefillDesc = $_POST['description'] ?? '';
if (!$usePosted && $prefillDesc === '' && $template) {
  $prefillDesc = trim((string)$template['name']);
}

// Construir filas a renderizar
$rowsData = [];
if ($usePosted) {
  $count = max(4, count($postedAccountIds));
  for ($i=0; $i<$count; $i++) {
    $rowsData[] = [
      'account_id' => (int)($_POST['account_id'][$i] ?? 0),
      'memo' => (string)($_POST['memo'][$i] ?? ''),
      'debit' => (string)($_POST['debit'][$i] ?? ''),
      'credit' => (string)($_POST['credit'][$i] ?? ''),
    ];
  }
} elseif (!empty($templateLines)) {
  $count = max(4, count($templateLines));
  for ($i=0; $i<$count; $i++) {
    $ln = $templateLines[$i] ?? null;
    $rowsData[] = [
      'account_id' => $ln ? (int)$ln['account_id'] : 0,
      'memo' => $ln ? (string)($ln['memo'] ?? '') : '',
      'debit' => $ln ? (string)((int)$ln['debit']) : '',
      'credit' => $ln ? (string)((int)$ln['credit']) : '',
    ];
  }
} else {
  for ($i=0; $i<4; $i++) {
    $rowsData[] = ['account_id'=>0,'memo'=>'','debit'=>'','credit'=>''];
  }
}

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <h2>Crear asiento</h2>

  <?php if (!empty($errors)): ?>
    <div class="alert danger">
      <ul style="margin:0; padding-left:18px">
        <?php foreach ($errors as $er): ?><li><?= h($er) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" class="card" id="entryForm">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="template_id" value="<?= (int)$selectedTemplateId ?>">

    <div class="row">
      <div class="field">
        <label>Fecha</label>
        <input type="date" name="entry_date" value="<?= h($prefillDate) ?>" required>
      </div>

      <div class="field" style="flex:1">
        <label style="display:flex;align-items:center;gap:8px;">
          Glosa
          <span
            tabindex="0"
            role="button"
            aria-label="Tip glosa"
            data-tip-title="Tip"
            data-tip="La glosa describe el asiento. Si aplicas una plantilla, se puede precargar con el nombre de la plantilla."
            style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:999px;border:1px solid var(--border);background:#fff;cursor:help;font-weight:800;font-size:12px;"
          >i</span>
        </label>
        <input name="description" value="<?= h($prefillDesc) ?>" required placeholder="Ej: Pago proveedor, Venta servicio, etc.">
      </div>

      <div class="field" style="min-width:260px;">
        <label style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
          <span>Plantilla</span>
          <a class="small" href="entry_templates.php" style="text-decoration:none;">Administrar</a>
        </label>

        <div style="display:flex; gap:8px;">
          <select id="templateSelect" style="flex:1;">
            <option value="0">— Sin plantilla —</option>
            <?php foreach ($templates as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= ((int)$t['id'] === (int)$selectedTemplateId) ? 'selected' : '' ?>>
                <?= h((string)$t['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <button type="button" class="btn secondary smallbtn" id="applyTplBtn">Aplicar</button>
        </div>

        <div class="small" style="margin-top:6px;">
          Aplica una plantilla para precargar las líneas del asiento.
        </div>
      </div>

      <div class="field" style="align-self:flex-end">
        <a class="btn secondary" href="entries.php">Volver</a>
      </div>
    </div>

    <h3 style="margin-top:10px">Líneas</h3>

    <div class="small">Tip: ingresa montos en CLP (sin decimales). El sistema valida Debe = Haber.</div>

    <table class="table" id="linesTable" style="margin-top:8px">
      <thead>
        <tr>
          <th style="width:40%">Cuenta</th>
          <th>Memo</th>
          <th class="right" style="width:140px">Debe</th>
          <th class="right" style="width:140px">Haber</th>
          <th style="width:90px">Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rowsData as $row): ?>
          <tr>
            <td>
              <select name="account_id[]">
                <option value="0">Selecciona...</option>
                <?php foreach ($accounts as $a): ?>
                  <?php $sel = ((int)$row['account_id'] === (int)$a['id']) ? 'selected' : ''; ?>
                  <option value="<?= (int)$a['id'] ?>" <?= $sel ?>>
                    <?= h($a['code']) ?> — <?= h($a['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input name="memo[]" value="<?= h($row['memo']) ?>" placeholder="(opcional)"></td>
            <td><input class="right money" name="debit[]" value="<?= h($row['debit']) ?>" placeholder="0"></td>
            <td><input class="right money" name="credit[]" value="<?= h($row['credit']) ?>" placeholder="0"></td>
            <td><button type="button" class="btn danger smallbtn" onclick="removeRow(this)">Quitar</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="2" class="right">Totales</th>
          <th class="right" id="totalD">$0</th>
          <th class="right" id="totalC">$0</th>
          <th></th>
        </tr>
      </tfoot>
    </table>

    <div style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap;">
      <button type="button" class="btn secondary" onclick="addRow()">+ Agregar línea</button>
      <button class="btn" type="submit">Guardar asiento</button>
    </div>
  </form>
</div>

<script>
function parseMoney(v){
  if(!v) return 0;
  // admite 1.000.000 / 1000000 / 1 000 000
  v = String(v).replace(/\./g,'').replace(/\s/g,'');
  v = v.replace(/,/g,'.');
  let n = Number(v);
  return isNaN(n) ? 0 : n;
}
function formatCLP(n){
  try { return '$' + Math.round(n).toLocaleString('es-CL'); }
  catch(e){ return '$' + Math.round(n); }
}

function recalc(){
  let td=0, tc=0;
  document.querySelectorAll('#linesTable tbody tr').forEach(tr=>{
    const d = tr.querySelector('input[name="debit[]"]');
    const c = tr.querySelector('input[name="credit[]"]');
    td += parseMoney(d.value);
    tc += parseMoney(c.value);
  });
  document.getElementById('totalD').textContent = formatCLP(td);
  document.getElementById('totalC').textContent = formatCLP(tc);
}

function addRow(){
  const tb = document.querySelector('#linesTable tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select name="account_id[]">
        <option value="0">Selecciona...</option>
        <?php foreach ($accounts as $a): ?>
          <option value="<?= (int)$a['id'] ?>"><?= h($a['code']) ?> — <?= h($a['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input name="memo[]" placeholder="(opcional)"></td>
    <td><input class="right money" name="debit[]" placeholder="0"></td>
    <td><input class="right money" name="credit[]" placeholder="0"></td>
    <td><button type="button" class="btn danger smallbtn" onclick="removeRow(this)">Quitar</button></td>
  `;
  tb.appendChild(tr);
  bindRow(tr);
  recalc();
}

function removeRow(btn){
  const tr = btn.closest('tr');
  tr.remove();
  recalc();
}

function bindRow(tr){
  tr.querySelectorAll('input.money').forEach(inp=>{
    inp.addEventListener('input', recalc);
    inp.addEventListener('blur', ()=>{
      const n = parseMoney(inp.value);
      inp.value = (n ? String(Math.round(n)) : '');
      recalc();
    });
  });
}

document.querySelectorAll('#linesTable tbody tr').forEach(bindRow);
recalc();

// Aplicar plantilla: redirige con template_id
document.getElementById('applyTplBtn').addEventListener('click', () => {
  const id = Number(document.getElementById('templateSelect').value || 0);
  const url = new URL(window.location.href);
  if (id > 0) url.searchParams.set('template_id', String(id));
  else url.searchParams.delete('template_id');
  window.location.href = url.toString();
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
