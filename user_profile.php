<?php
require_once __DIR__ . '/lib/helpers.php';
require_login();

$pdo = db();
$u = current_user();
$user_id = (int)$u['id'];

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');

  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = (string)($_POST['password'] ?? '');
  $confirm = (string)($_POST['confirm_password'] ?? '');

  if ($name === '') $errors[] = "El nombre es obligatorio";
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "El email no es válido";

  // Email único
  if (!$errors) {
    $st = $pdo->prepare("SELECT 1 FROM users WHERE email=? AND id<>? LIMIT 1");
    $st->execute([$email, $user_id]);
    if ($st->fetchColumn()) $errors[] = "El email ya está registrado";
  }

  // Password opcional
  if ($password !== '') {
    if (strlen($password) < 6) $errors[] = "La contraseña debe tener al menos 6 caracteres";
    if ($password !== $confirm) $errors[] = "Las contraseñas no coinciden";
  }

  if (!$errors) {
    if ($password !== '') {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $st = $pdo->prepare("UPDATE users SET name=?, email=?, password_hash=? WHERE id=?");
      $st->execute([$name,$email,$hash,$user_id]);
    } else {
      $st = $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=?");
      $st->execute([$name,$email,$user_id]);
    }

    // Mantener sesión sincronizada
    $_SESSION['user']['name'] = $name;
    $_SESSION['user']['email'] = $email;

    $success = "Datos actualizados correctamente";
  }
}

$st = $pdo->prepare("SELECT name,email FROM users WHERE id=?");
$st->execute([$user_id]);
$user = $st->fetch(PDO::FETCH_ASSOC);

require __DIR__ . '/partials/header.php';
?>
<div class="card" style="max-width:640px;margin:20px auto;">
  <h2>Mi perfil</h2>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $e) echo "<p>".h($e)."</p>"; ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><p><?= h($success) ?></p></div>
  <?php endif; ?>

  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="field">
      <label>Nombre</label>
      <input name="name" required value="<?= h($user['name'] ?? '') ?>">
    </div>

    <div class="field">
      <label>Email</label>
      <input name="email" type="email" required value="<?= h($user['email'] ?? '') ?>">
    </div>

    <hr>

    <div class="field">
      <label>Nueva contraseña (opcional)</label>
      <input name="password" type="password" autocomplete="new-password">
    </div>

    <div class="field">
      <label>Confirmar contraseña</label>
      <input name="confirm_password" type="password" autocomplete="new-password">
    </div>

    <button class="btn" style="margin-top:10px">Guardar cambios</button>
    <a class="btn" href="index.php" style="margin-top:10px">Volver</a>
  </form>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
