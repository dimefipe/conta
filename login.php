<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

if (is_logged_in()) redirect('index.php');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');

  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');

  $st = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch();

  if ($u && password_verify($pass, $u['password_hash'])) {
    // 1) login user (tu sesión actual)
    login_user((int)$u['id'], $u['email'], $u['name']);

    // 2) set empresa activa (primera empresa disponible del usuario)
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

    // 3) si no tiene empresas, lo mandamos a crear una
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
      <input name="email" type="email" required />
    </div>

    <div class="field">
      <label>Contraseña</label>
      <input name="password" type="password" required />
    </div>

    <button class="btn" style="margin-top:10px">Entrar</button>
  </form>

  <div class="small">
    Si es primera vez: entra a <b>setup_admin.php</b> para crear el usuario inicial.
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
