<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

$pdo = db();
$count = (int)$pdo->query("SELECT COUNT(*) c FROM users")->fetch()['c'];

if ($count > 0) {
  die("Ya existe un usuario. Borra este archivo setup_admin.php por seguridad.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $name = trim($_POST['name'] ?? '');
  $pass = (string)($_POST['password'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($pass) < 6) {
    flash_set('err','Email válido, nombre y contraseña (mín 6) son obligatorios.');
    redirect('setup_admin.php');
  }

  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $st = $pdo->prepare("INSERT INTO users(email,name,password_hash) VALUES(?,?,?)");
  $st->execute([$email,$name,$hash]);

  flash_set('ok','Usuario admin creado. Ahora entra a login.php y luego BORRA setup_admin.php.');
  redirect('login.php');
}

require __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Crear Admin Inicial</h2>
  <p class="small">Se crea solo si no existen usuarios. Luego borra este archivo.</p>

  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <div class="row">
      <div class="field" style="flex:1">
        <label>Email</label>
        <input name="email" type="email" required />
      </div>
      <div class="field" style="flex:1">
        <label>Nombre</label>
        <input name="name" required />
      </div>
      <div class="field" style="flex:1">
        <label>Contraseña</label>
        <input name="password" type="password" minlength="6" required />
      </div>
    </div>
    <button class="btn" style="margin-top:10px">Crear admin</button>
  </form>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
