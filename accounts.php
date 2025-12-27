<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');
  $code = trim($_POST['code'] ?? '');
  $name = trim($_POST['name'] ?? '');
  $type = $_POST['type'] ?? '';
  if ($code==='' || $name==='' || !in_array($type,['ASSET','LIABILITY','EQUITY','INCOME','COST','EXPENSE'],true)) {
    flash_set('err','Completa código, nombre y tipo válido.');
    redirect('accounts.php');
  }
  try {
    $st = $pdo->prepare("INSERT INTO accounts(code,name,type) VALUES(?,?,?)");
    $st->execute([$code,$name,$type]);
    flash_set('ok','Cuenta creada.');
  } catch (Throwable $e) {
    flash_set('err','Error: ' . $e->getMessage());
  }
  redirect('accounts.php');
}

$rows = $pdo->query("SELECT * FROM accounts ORDER BY code")->fetchAll();
require __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Plan de cuentas</h2>

  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
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

  <table class="table">
    <thead><tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Activa</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r['code']) ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h($r['type']) ?></td>
          <td><?= $r['is_active'] ? 'Sí' : 'No' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
