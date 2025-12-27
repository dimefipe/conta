<?php
require_once __DIR__ . '/lib/helpers.php';

require_login();
require_company_selected();

$pdo = db();
$cid = current_company_id();

$TYPES = ['ASSET','LIABILITY','EQUITY','INCOME','COST','EXPENSE'];

/**
 * Helper: obtener cuenta SOLO si pertenece a la empresa activa
 */
function get_account_in_company(PDO $pdo, int $id, int $cid): ?array {
  $st = $pdo->prepare("SELECT * FROM accounts WHERE id=? AND company_id=? LIMIT 1");
  $st->execute([$id, $cid]);
  $r = $st->fetch();
  return $r ?: null;
}

/**
 * CREATE
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  csrf_check($_POST['csrf'] ?? '');

  $code = trim($_POST['code'] ?? '');
  $name = trim($_POST['name'] ?? '');
  $type = $_POST['type'] ?? '';

  if ($code === '' || $name === '' || !in_array($type, $TYPES, true)) {
    flash_set('err','Completa código, nombre y tipo válido.');
    redirect('accounts.php');
  }

  try {
    $st = $pdo->prepare("INSERT INTO accounts(company_id, code, name, type) VALUES(?,?,?,?)");
    $st->execute([$cid, $code, $name, $type]);
    flash_set('ok','Cuenta creada.');
  } catch (Throwable $e) {
    flash_set('err','Error: ' . $e->getMessage());
  }
  redirect('accounts.php');
}

/**
 * UPDATE
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
  csrf_check($_POST['csrf'] ?? '');

  $id = (int)($_POST['id'] ?? 0);
  $code = trim($_POST['code'] ?? '');
  $name = trim($_POST['name'] ?? '');
  $type = $_POST['type'] ?? '';
  $is_active = isset($_POST['is_active']) ? 1 : 0;

  if ($id <= 0 || $code === '' || $name === '' || !in_array($type, $TYPES, true)) {
    flash_set('err','Datos inválidos para editar.');
    redirect('accounts.php');
  }

  // Blindaje: la cuenta debe pertenecer a la empresa activa
  $acc = get_account_in_company($pdo, $id, $cid);
  if (!$acc) {
    flash_set('err','Cuenta no encontrada para esta empresa.');
    redirect('accounts.php');
  }

  try {
    // UNIQUE (company_id, code) evita duplicados dentro de la misma empresa
    $st = $pdo->prepare("UPDATE accounts SET code=?, name=?, type=?, is_active=? WHERE id=? AND company_id=?");
    $st->execute([$code, $name, $type, $is_active, $id, $cid]);
    flash_set('ok','Cuenta actualizada.');
  } catch (Throwable $e) {
    flash_set('err','Error: ' . $e->getMessage());
  }
  redirect('accounts.php');
}

/**
 * DELETE (si tiene movimientos => desactiva, si no => borra)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  csrf_check($_POST['csrf'] ?? '');

  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) redirect('accounts.php');

  // Blindaje: la cuenta debe pertenecer a la empresa activa
  $acc = get_account_in_company($pdo, $id, $cid);
  if (!$acc) {
    flash_set('err','Cuenta no encontrada para esta empresa.');
    redirect('accounts.php');
  }

  // movimientos SOLO de la empresa activa (por seguridad)
  $cnt = $pdo->prepare("
    SELECT COUNT(*) c
    FROM journal_lines jl
    JOIN journal_entries je ON je.id = jl.entry_id
    WHERE jl.account_id = ? AND je.company_id = ?
  ");
  $cnt->execute([$id, $cid]);
  $hasMoves = ((int)$cnt->fetchColumn()) > 0;

  try {
    if ($hasMoves) {
      $st = $pdo->prepare("UPDATE accounts SET is_active=0 WHERE id=? AND company_id=?");
      $st->execute([$id, $cid]);
      flash_set('ok','Cuenta con movimientos: se desactivó (no se elimina).');
    } else {
      $st = $pdo->prepare("DELETE FROM accounts WHERE id=? AND company_id=?");
      $st->execute([$id, $cid]);
      flash_set('ok','Cuenta eliminada.');
    }
  } catch (Throwable $e) {
    flash_set('err','Error: ' . $e->getMessage());
  }
  redirect('accounts.php');
}

/**
 * IMPORT CSV (Excel -> Guardar como CSV)
 * Columnas: code,name,type,is_active (is_active opcional)
 * Aplica por empresa activa (company_id)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_csv') {
  csrf_check($_POST['csrf'] ?? '');

  if (empty($_FILES['csv']['tmp_name'])) {
    flash_set('err','Sube un CSV.');
    redirect('accounts.php');
  }

  $fh = fopen($_FILES['csv']['tmp_name'], 'r');
  if (!$fh) {
    flash_set('err','No se pudo leer el CSV.');
    redirect('accounts.php');
  }

  $pdo->beginTransaction();
  try {
    $up = $pdo->prepare("
      INSERT INTO accounts(company_id, code, name, type, is_active)
      VALUES(?,?,?,?,?)
      ON DUPLICATE KEY UPDATE
        name=VALUES(name),
        type=VALUES(type),
        is_active=VALUES(is_active)
    ");

    $row = 0; $ok = 0;
    while (($data = fgetcsv($fh, 0, ",")) !== false) {
      $row++;

      // encabezado opcional
      if ($row === 1 && isset($data[0]) && strtolower(trim($data[0])) === 'code') continue;

      $code = trim($data[0] ?? '');
      $name = trim($data[1] ?? '');
      $type = trim($data[2] ?? '');
      $active = isset($data[3]) ? (int)$data[3] : 1;

      if ($code === '' || $name === '' || !in_array($type, $TYPES, true)) {
        continue;
      }

      $active = $active ? 1 : 0;
      $up->execute([$cid, $code, $name, $type, $active]);
      $ok++;
    }

    fclose($fh);
    $pdo->commit();
    flash_set('ok',"Importación OK (empresa activa). Filas aplicadas: $ok");
  } catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('err','Error importando: ' . $e->getMessage());
  }
  redirect('accounts.php');
}

/**
 * Edit mode (solo de la empresa activa)
 */
$editId = (int)($_GET['edit_id'] ?? 0);
$editAcc = null;
if ($editId > 0) {
  $editAcc = get_account_in_company($pdo, $editId, $cid);
  if (!$editAcc) {
    flash_set('err','Esa cuenta no existe en la empresa activa.');
    redirect('accounts.php');
  }
}

/**
 * Listado SOLO de la empresa activa
 */
$st = $pdo->prepare("SELECT * FROM accounts WHERE company_id=? ORDER BY code");
$st->execute([$cid]);
$rows = $st->fetchAll();

require __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Plan de cuentas</h2>

  <!-- Crear -->
  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">

    <div class="row">
      <div class="field">
        <label>Código</label>
        <input name="code" placeholder="Ej: 4106" required />
      </div>

      <div class="field" style="flex:1">
        <label>Nombre</label>
        <input name="name" placeholder="Ej: Ingresos - SEO" required />
      </div>

      <div class="field">
        <label>Tipo</label>
        <select name="type" required>
          <option value="ASSET">ASSET (Activo)</option>
          <option value="LIABILITY">LIABILITY (Pasivo)</option>
          <option value="EQUITY">EQUITY (Patrimonio)</option>
          <option value="INCOME">INCOME (Ingreso)</option>
          <option value="COST">COST (Costo)</option>
          <option value="EXPENSE">EXPENSE (Gasto)</option>
        </select>
      </div>
    </div>

    <div style="margin-top:10px">
      <button class="btn">Agregar</button>
    </div>
  </form>

  <!-- Editar -->
  <?php if ($editAcc): ?>
    <form method="post" class="card" style="margin-top:12px">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$editAcc['id'] ?>">

      <h3>Editar cuenta</h3>
      <div class="row">
        <div class="field">
          <label>Código</label>
          <input name="code" value="<?= h($editAcc['code']) ?>" required />
        </div>

        <div class="field" style="flex:1">
          <label>Nombre</label>
          <input name="name" value="<?= h($editAcc['name']) ?>" required />
        </div>

        <div class="field">
          <label>Tipo</label>
          <select name="type" required>
            <?php foreach (['ASSET','LIABILITY','EQUITY','INCOME','COST','EXPENSE'] as $t): ?>
              <option value="<?= h($t) ?>" <?= ($editAcc['type'] === $t) ? 'selected' : '' ?>>
                <?= h($t) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field" style="align-self:flex-end">
          <label style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="is_active" <?= $editAcc['is_active'] ? 'checked' : '' ?>>
            Activa
          </label>
        </div>
      </div>

      <div style="margin-top:10px">
        <button class="btn">Guardar cambios</button>
        <a class="btn secondary" href="accounts.php">Cancelar</a>
      </div>
    </form>
  <?php endif; ?>

  <!-- Importar CSV -->
  <form method="post" class="card" enctype="multipart/form-data" style="margin-top:12px">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="import_csv">
    <h3>Importar plan de cuentas (CSV desde Excel)</h3>
    <div class="small">Se aplica SOLO a la <b>empresa activa</b>. Columnas: <b>code,name,type,is_active</b> (is_active opcional).</div>

    <div class="row" style="margin-top:10px">
      <div class="field" style="flex:1">
        <label>Archivo CSV</label>
        <input type="file" name="csv" accept=".csv,text/csv" required>
      </div>
      <div class="field" style="align-self:flex-end">
        <button class="btn">Importar</button>
      </div>
    </div>
  </form>

  <table class="table" style="margin-top:12px">
    <thead>
      <tr>
        <th>Código</th>
        <th>Nombre</th>
        <th>Tipo</th>
        <th>Activa</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r['code']) ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h($r['type']) ?></td>
          <td><?= $r['is_active'] ? 'Sí' : 'No' ?></td>
          <td style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn secondary smallbtn" href="accounts.php?edit_id=<?= (int)$r['id'] ?>">Editar</a>

            <form method="post" style="display:inline-block" onsubmit="return confirm('¿Eliminar / desactivar esta cuenta?');">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn danger smallbtn" type="submit">Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
