<?php
require_once __DIR__ . '/lib/helpers.php'; // helpers ya incluye db.php

if (is_logged_in()) redirect('index.php');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');

  $email = strtolower(trim($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  $st = $pdo->prepare("SELECT id,email,name,password_hash FROM users WHERE email=? LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch();

  if ($u && password_verify($pass, $u['password_hash'])) {
    // login user
    login_user((int)$u['id'], (string)$u['email'], (string)$u['name']);

    // set empresa activa (primera empresa disponible del usuario)
    $st2 = $pdo->prepare("
      SELECT c.id
      FROM companies c
      JOIN user_companies uc ON uc.company_id = c.id
      WHERE uc.user_id = ?
      ORDER BY c.id ASC
      LIMIT 1
    ");
    $st2->execute([(int)$u['id']]);
    $cid = (int)($st2->fetchColumn() ?: 0);

    if ($cid > 0) {
      set_company_id($cid);
      flash_set('ok','Sesión iniciada.');
      redirect('index.php');
    }

    unset($_SESSION['company_id']);
    flash_set('err','Sesión iniciada, pero no tienes empresas. Crea una para comenzar.');
    redirect('companies.php');
  }

  flash_set('err','Credenciales incorrectas.');
  redirect('login.php');
}

require __DIR__ . '/partials/header.php';
?>
<div class="card" style="max-width:520px;margin:20px auto;">
  <h2>Login</h2>

  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="field">
      <label>Email</label>
      <input name="email" type="email" required autocomplete="email" />
    </div>

    <div class="field">
      <label>Contraseña</label>
      <input name="password" type="password" data-pw-toggle="1" required autocomplete="current-password" />
    </div>

    <button class="btn" style="margin-top:10px">Entrar</button>

    <div class="row" style="justify-content:space-between; margin-top:10px;">
      <a class="small" href="forgot_password.php">¿Olvidaste tu contraseña?</a>
      <a class="small" href="register.php">Crear cuenta</a>
    </div>
  </form>

  <div class="small" style="margin-top:10px">
    Si es primera vez: entra a <b>setup_admin.php</b> para crear el usuario inicial.
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
