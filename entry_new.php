<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();
require_company_selected();

$pdo = db();
$cid = current_company_id();

// Cuentas activas de la empresa
$accSt = $pdo->prepare("SELECT id, code, name, type FROM accounts WHERE company_id=? AND is_active=1 ORDER BY code");
$accSt->execute([$cid]);
$accounts = $accSt->fetchAll();

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

    // permite "1000", "1.000", "1,000" -> los normalizamos
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

  // validar que todas las cuentas sean de la empresa activa (extra seguridad)
  if (!$errors && $lines) {
    $ids = array_map(fn($l)=>$l['account_id'], $lines);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $check = $pdo->prepare("SELECT COUNT(*) c FROM accounts WHERE company_id=? AND id IN ($placeholders)");
    $check->execute(array_merge([$cid], $ids));
    $countOk = (int)$check->fetch()['c'];
    if ($countOk !== count(array_unique($ids))) {
      $errors[] = 'Hay cuentas que no pertenecen a la empresa activa.';
    }
  }

  if (!$errors) {
    $pdo->beginTransaction();
    try {
      $st = $pdo->prepare("INSERT INTO journal_entries(company_id, entry_date, description, status) VALUES(?,?,?, 'POSTED')");
      $st->execute([$cid, $date, $desc]);
      $entryId = (int)$pdo->lastInsertId();

      $ins = $pdo->prepare("INSERT INTO journal_lines(entry_id, line_no, account_id, memo, debit, credit) VALUES(?,?,?,?,?,?)");
      $ln = 1;
      foreach ($lines as $l) {
        $ins->execute([$entryId, $ln, $l['account_id'], $l['memo'], $l['debit'], $l['credit']]);
        $ln++;
      }

      $pdo->commit();
      flash_set('ok', 'Asiento creado.');
      redirect('entry_view.php?id=' . $entryId);
    } catch (Throwable $e) {
      $pdo->rollBack();
      $errors[] = 'Error guardando: ' . $e->getMessage();
    }
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

    <div class="row">
      <div class="field">
        <label>Fecha</label>
        <input type="date" name="entry_date" value="<?= h($_POST['entry_date'] ?? date('Y-m-d')) ?>" required>
      </div>
      <div class="field" style="flex:1">
        <label>Glosa</label>
        <input name="description" value="<?= h($_POST['description'] ?? '') ?>" required placeholder="Ej: Pago proveedor, Venta servicio, etc.">
      </div>
      <div class="field" style="align-self:flex-end">
        <a class="btn secondary" href="entries.php">Volver</a>
      </div>
    </div>

    <h3 style="margin-top:10px">Líneas</h3>

    <div class="small">Tip: ingresa montos en CLP (sin decimales). El sistema igual valida Debe = Haber.</div>

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
        <?php
          $postedLines = $_POST['account_id'] ?? [];
          $rowCount = max(4, count($postedLines));
          for ($i=0; $i<$rowCount; $i++):
        ?>
        <tr>
          <td>
            <select name="account_id[]">
              <option value="0">Selecciona...</option>
              <?php foreach ($accounts as $a): ?>
                <?php $sel = ((int)($postedLines[$i] ?? 0) === (int)$a['id']) ? 'selected' : ''; ?>
                <option value="<?= (int)$a['id'] ?>" <?= $sel ?>>
                  <?= h($a['code']) ?> — <?= h($a['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input name="memo[]" value="<?= h($_POST['memo'][$i] ?? '') ?>" placeholder="(opcional)"></td>
          <td><input class="right money" name="debit[]" value="<?= h($_POST['debit'][$i] ?? '') ?>" placeholder="0"></td>
          <td><input class="right money" name="credit[]" value="<?= h($_POST['credit'][$i] ?? '') ?>" placeholder="0"></td>
          <td><button type="button" class="btn danger smallbtn" onclick="removeRow(this)">Quitar</button></td>
        </tr>
        <?php endfor; ?>
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

    <div style="display:flex; gap:10px; margin-top:10px">
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
  // sin decimales
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
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
