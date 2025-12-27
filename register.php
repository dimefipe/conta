<?php
require_once __DIR__ . '/lib/helpers.php';
$pdo = db();

if (is_logged_in()) redirect('index.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');

  $name  = trim($_POST['name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  $confirm  = (string)($_POST['confirm_password'] ?? '');

  // Validaciones
  if ($name === '') $errors[] = "El nombre es obligatorio";
  if (!validate_email($email)) $errors[] = "El email no es válido";
  if (!validate_password($password, 6)) $errors[] = "La contraseña debe tener al menos 6 caracteres";
  if ($password !== $confirm) $errors[] = "Las contraseñas no coinciden";

  // Email único
  if (!$errors) {
    $st = $pdo->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    if ($st->fetchColumn()) $errors[] = "El email ya está registrado";
  }

  // Crear usuario
  if (!$errors) {
    try {
      $hash = password_hash($password, PASSWORD_DEFAULT);

      $st = $pdo->prepare("
        INSERT INTO users (name, email, password_hash, created_at)
        VALUES (?,?,?, NOW())
      ");
      $st->execute([$name, $email, $hash]);

      $userId = (int)$pdo->lastInsertId();

      // Auto-login
      login_user($userId, $email, $name);

      // Sin empresa: lo mandamos a crear
      unset($_SESSION['company_id']);
      flash_set('ok', 'Cuenta creada. Ahora crea tu primera empresa.');
      redirect('companies.php');

    } catch (Throwable $e) {
      $errors[] = "Error al registrar: " . $e->getMessage();
    }
  }
}

require __DIR__ . '/partials/header.php';
?>
<div class="card" style="max-width:560px;margin:20px auto;">
  <h2>Registro</h2>

  <?php if ($errors): ?>
    <div class="alert err">
      <?php foreach ($errors as $e): ?>
        <p><?= h($e) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="field">
      <label>Nombre</label>
      <input name="name" required value="<?= h($_POST['name'] ?? '') ?>" autocomplete="name">
    </div>

    <div class="field">
      <label>Email</label>
      <input name="email" type="email" required value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email">
    </div>

    <div class="field">
      <label>Contraseña</label>
      <input name="password" type="password" data-pw-toggle="1" required autocomplete="new-password">
    </div>

    <div class="field">
      <label>Confirmar contraseña</label>
      <input name="confirm_password" type="password" data-pw-toggle="1" required autocomplete="new-password">
    </div>

    <button class="btn" style="margin-top:10px">Crear cuenta</button>
    <a class="btn secondary" href="login.php" style="margin-top:10px">Volver al login</a>
  </form>

  <div class="small" style="margin-top:10px">
    ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
