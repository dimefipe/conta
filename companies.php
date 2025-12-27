<?php
require_once __DIR__ . '/lib/helpers.php';

require_login();

$pdo = db();
$user = current_user();
$userId = (int)$user['id'];

$errors = [];
$action = $_GET['action'] ?? '';
$editId = (int)($_GET['id'] ?? 0);

function fetch_user_companies(PDO $pdo, int $userId): array {
  $st = $pdo->prepare("
    SELECT c.*
    FROM companies c
    JOIN user_companies uc ON uc.company_id = c.id
    WHERE uc.user_id = ?
    ORDER BY c.created_at DESC, c.id DESC
  ");
  $st->execute([$userId]);
  return $st->fetchAll();
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');
  $op = $_POST['op'] ?? '';

  if ($op === 'create') {
    $name = trim($_POST['name'] ?? '');
    $rut  = trim($_POST['rut'] ?? '');

    if ($name === '') $errors[] = 'Nombre requerido.';

    if (!$errors) {
      $pdo->beginTransaction();
      try {
        $st = $pdo->prepare("INSERT INTO companies(name, rut) VALUES(?, ?)");
        $st->execute([$name, $rut]);
        $newId = (int)$pdo->lastInsertId();

        $link = $pdo->prepare("INSERT INTO user_companies(user_id, company_id) VALUES(?, ?)");
        $link->execute([$userId, $newId]);

        set_company_id($newId);

        $pdo->commit();
        flash_set('ok', 'Empresa creada y seleccionada.');
        redirect('companies.php');
      } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Error creando empresa: ' . $e->getMessage();
      }
    }
  }

  if ($op === 'update') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $rut  = trim($_POST['rut'] ?? '');

    if ($id <= 0) $errors[] = 'Empresa inválida.';
    if ($name === '') $errors[] = 'Nombre requerido.';

    if (!$errors) {
      if (!user_has_company($userId, $id)) {
        $errors[] = 'No tienes acceso a esta empresa.';
      }
    }

    if (!$errors) {
      try {
        $st = $pdo->prepare("UPDATE companies SET name=?, rut=? WHERE id=?");
        $st->execute([$name, $rut, $id]);
        flash_set('ok', 'Empresa actualizada.');
        redirect('companies.php');
      } catch (Throwable $e) {
        $errors[] = 'Error actualizando: ' . $e->getMessage();
      }
    }
  }

  if ($op === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) $errors[] = 'Empresa inválida.';

    if (!$errors && !user_has_company($userId, $id)) {
      $errors[] = 'No tienes acceso a esta empresa.';
    }

    if (!$errors) {
      $pdo->beginTransaction();
      try {
        // desvincular usuario
        $pdo->prepare("DELETE FROM user_companies WHERE user_id=? AND company_id=?")
            ->execute([$userId, $id]);

        // si nadie más está vinculado, borrar empresa (y cascadas)
        $chk = $pdo->prepare("SELECT COUNT(*) c FROM user_companies WHERE company_id=?");
        $chk->execute([$id]);
        $cnt = (int)$chk->fetchColumn();

        if ($cnt === 0) {
          $pdo->prepare("DELETE FROM companies WHERE id=?")->execute([$id]);
        }

        // si era la activa, limpiar para que require_company_selected elija otra
        if (current_company_id() === $id) {
          unset($_SESSION['company_id']);
        }

        $pdo->commit();
        flash_set('ok', 'Empresa eliminada (o desvinculada).');
        redirect('companies.php');
      } catch (Throwable $e) {
        $pdo->rollBack();
        $errors[] = 'Error eliminando: ' . $e->getMessage();
      }
    }
  }
}

// Empresa en edición (si aplica)
$edit = null;
if ($action === 'edit' && $editId > 0 && user_has_company($userId, $editId)) {
  $st = $pdo->prepare("SELECT * FROM companies WHERE id=?");
  $st->execute([$editId]);
  $edit = $st->fetch();
}

// Listado
$companies = fetch_user_companies($pdo, $userId);

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <h2>Empresas</h2>

  <?php if ($errors): ?>
    <div class="alert danger">
      <ul style="margin:0; padding-left:18px">
        <?php foreach ($errors as $er): ?>
          <li><?= h($er) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="row" style="align-items:flex-start">
    <div class="card" style="flex:1; min-width:320px">
      <h3><?= $edit ? 'Editar empresa' : 'Nueva empresa' ?></h3>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <?php if ($edit): ?>
          <input type="hidden" name="op" value="update">
          <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
        <?php else: ?>
          <input type="hidden" name="op" value="create">
        <?php endif; ?>

        <div class="row">
          <div class="field" style="flex:1; min-width:240px">
            <label>Nombre</label>
            <input name="name" value="<?= h($edit['name'] ?? '') ?>" placeholder="Ej: Empresa A">
          </div>

          <div class="field" style="min-width:220px">
            <label>RUT (opcional)</label>
            <input name="rut" value="<?= h($edit['rut'] ?? '') ?>" placeholder="Ej: 76.123.456-7">
          </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap">
          <button class="btn" type="submit"><?= $edit ? 'Guardar' : 'Crear' ?></button>
          <?php if ($edit): ?>
            <a class="btn secondary" href="companies.php">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card" style="flex:2; min-width:420px">
      <h3>Mis empresas</h3>

      <table class="table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>RUT</th>
            <th>Activa</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$companies): ?>
            <tr><td colspan="4" class="small">Aún no tienes empresas.</td></tr>
          <?php else: ?>
            <?php foreach ($companies as $c): ?>
              <tr>
                <td><?= h($c['name']) ?></td>
                <td><?= h($c['rut'] ?? '') ?></td>
                <td><?= (current_company_id() === (int)$c['id']) ? '✅' : '' ?></td>
                <td style="display:flex; gap:8px; flex-wrap:wrap">
                  <form method="post" action="switch_company.php">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="company_id" value="<?= (int)$c['id'] ?>">
                    <button class="btn secondary smallbtn" type="submit">Usar</button>
                  </form>

                  <a class="btn secondary smallbtn" href="companies.php?action=edit&id=<?= (int)$c['id'] ?>">Editar</a>

                  <form method="post" onsubmit="return confirm('Esto puede borrar la contabilidad de esta empresa. ¿Seguro?');">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="op" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn danger smallbtn" type="submit">Eliminar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>

      <div class="small" style="margin-top:10px">
        Tip: si en el futuro quieres “roles” o “empresas compartidas”, lo añadimos con un campo role en user_companies.
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
