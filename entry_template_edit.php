<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_login();
require_company_selected();

$pdo = db();
$cid = (int) current_company_id();
$user = current_user();

function load_accounts(PDO $pdo, int $cid): array {
  $st = $pdo->prepare("SELECT id, code, name FROM accounts WHERE company_id = ? AND is_active = 1 ORDER BY code ASC");
  $st->execute([$cid]);
  return $st->fetchAll() ?: [];
}

function validate_lines(array $post): array {
  $accs  = $post['account_id'] ?? [];
  $memos = $post['memo'] ?? [];
  $debs  = $post['debit'] ?? [];
  $creds = $post['credit'] ?? [];

  $lines = [];
  $d = 0.0; $c = 0.0;

  for ($i=0; $i<count($accs); $i++) {
    $account_id = (int)($accs[$i] ?? 0);
    $memo = trim((string)($memos[$i] ?? ''));

    $debit  = (float) preg_replace('/[^\d.]/', '', (string)($debs[$i] ?? '0'));
    $credit = (float) preg_replace('/[^\d.]/', '', (string)($creds[$i] ?? '0'));

    if ($account_id <= 0) continue;

    if ($debit > 0 && $credit > 0) return [[], 'Una línea no puede tener Debe y Haber al mismo tiempo.'];
    if ($debit <= 0 && $credit <= 0) return [[], 'Cada línea debe tener Debe o Haber.'];

    $d += $debit;
    $c += $credit;

    $lines[] = [
      'account_id' => $account_id,
      'memo' => $memo,
      'debit' => (int) round($debit),
      'credit' => (int) round($credit),
    ];
  }

  if (count($lines) < 2) return [[], 'Una plantilla debe tener al menos 2 líneas.'];
  if ($d <= 0 || abs($d - $c) > 0.5) return [[], 'Debe y Haber deben cuadrar para guardar la plantilla.'];

  return [$lines, ''];
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { flash_set('err','Plantilla inválida.'); redirect('entry_templates.php'); }

$accounts = load_accounts($pdo, $cid);

// cargar plantilla
$st = $pdo->prepare("SELECT * FROM entry_templates WHERE id = ? AND company_id = ?");
$st->execute([$id, $cid]);
$tpl = $st->fetch();
if (!$tpl) { flash_set('err','Plantilla no encontrada.'); redirect('entry_templates.php'); }

// cargar líneas
$st = $pdo->prepare("
  SELECT l.*, a.code, a.name AS account_name
  FROM entry_template_lines l
  JOIN accounts a ON a.id = l.account_id
  WHERE l.template_id = ?
  ORDER BY l.sort_order ASC
");
$st->execute([$id]);
$lines = $st->fetchAll() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');

  $name = trim((string)($_POST['name'] ?? ''));
  $description = trim((string)($_POST['description'] ?? ''));

  if ($name === '') {
    flash_set('err', 'El nombre de la plantilla es obligatorio.');
    redirect('entry_template_edit.php?id='.$id);
  }

  [$newLines, $err] = validate_lines($_POST);
  if ($err) {
    flash_set('err', $err);
    redirect('entry_template_edit.php?id='.$id);
  }

  // Validar cuentas por empresa
  $ids = array_values(array_unique(array_map(fn($l)=> (int)$l['account_id'], $newLines)));
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $st = $pdo->prepare("SELECT COUNT(*) AS c FROM accounts WHERE company_id = ? AND id IN ($in)");
  $st->execute(array_merge([$cid], $ids));
  $okCount = (int)($st->fetch()['c'] ?? 0);
  if ($okCount !== count($ids)) {
    flash_set('err', 'Hay cuentas inválidas (no pertenecen a la empresa).');
    redirect('entry_template_edit.php?id='.$id);
  }

  $pdo->beginTransaction();

  $st = $pdo->prepare("
    UPDATE entry_templates
    SET name = ?, description = ?, updated_at = NOW()
    WHERE id = ? AND company_id = ?
  ");
  $st->execute([$name, ($description !== '' ? $description : null), $id, $cid]);

  // reemplazar líneas (simple y seguro)
  $pdo->prepare("DELETE FROM entry_template_lines WHERE template_id = ?")->execute([$id]);

  $stl = $pdo->prepare("
    INSERT INTO entry_template_lines (template_id, sort_order, account_id, memo, debit, credit)
    VALUES (?, ?, ?, ?, ?, ?)
  ");

  $order = 1;
  foreach ($newLines as $ln) {
    $stl->execute([
      $id,
      $order++,
      (int)$ln['account_id'],
      ($ln['memo'] !== '' ? $ln['memo'] : null),
      (int)$ln['debit'],
      (int)$ln['credit'],
    ]);
  }

  $pdo->commit();

  flash_set('ok', 'Plantilla actualizada.');
  redirect('entry_templates.php');
}

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <h2 style="margin:0 0 10px;">Editar plantilla</h2>
</div>

<form method="post" class="card">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="row">
    <div class="field" style="flex:1;min-width:260px;">
      <label>Nombre</label>
      <input name="name" value="<?= h((string)$tpl['name']) ?>" required>
    </div>

    <div class="field" style="flex:2;min-width:260px;">
      <label>Descripción (opcional)</label>
      <input name="description" value="<?= h((string)($tpl['description'] ?? '')) ?>">
    </div>
  </div>

  <div style="margin-top:14px;" class="table-wrap">
    <table class="table" id="linesTable">
      <thead>
        <tr>
          <th style="width:260px;">Cuenta</th>
          <th>Glosa</th>
          <th style="width:160px;">Debe</th>
          <th style="width:160px;">Haber</th>
          <th style="width:80px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lines as $ln): ?>
          <tr>
            <td>
              <select name="account_id[]">
                <option value="">— Seleccionar —</option>
                <?php foreach ($accounts as $a): ?>
                  <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id'] === (int)$ln['account_id']) ? 'selected' : '' ?>>
                    <?= h($a['code'].' — '.$a['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input name="memo[]" value="<?= h((string)($ln['memo'] ?? '')) ?>" placeholder="Opcional"></td>
            <td><input name="debit[]" value="<?= (int)$ln['debit'] ?>" inputmode="numeric" placeholder="0"></td>
            <td><input name="credit[]" value="<?= (int)$ln['credit'] ?>" inputmode="numeric" placeholder="0"></td>
            <td><button class="btn secondary smallbtn removeLine" type="button">Quitar</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <template id="lineTemplate">
    <tr>
      <td>
        <select name="account_id[]">
          <option value="">— Seleccionar —</option>
          <?php foreach ($accounts as $a): ?>
            <option value="<?= (int)$a['id'] ?>"><?= h($a['code'].' — '.$a['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input name="memo[]" placeholder="Opcional"></td>
      <td><input name="debit[]" inputmode="numeric" placeholder="0"></td>
      <td><input name="credit[]" inputmode="numeric" placeholder="0"></td>
      <td><button class="btn secondary smallbtn removeLine" type="button">Quitar</button></td>
    </tr>
  </template>

  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;">
    <button class="btn secondary" id="addLine" type="button">+ Agregar línea</button>

    <div class="small" style="display:flex;gap:14px;align-items:center;">
      <span>Debe: <b id="debitTotal">0.00</b></span>
      <span>Haber: <b id="creditTotal">0.00</b></span>
      <span>Diferencia: <b id="diffTotal">0.00</b></span>
    </div>

    <div style="display:flex;gap:10px;">
      <a class="btn secondary" href="entry_templates.php">Cancelar</a>
      <button class="btn" id="saveEntry" type="submit">Guardar cambios</button>
    </div>
  </div>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
