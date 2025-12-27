<?php
// forgot_password.php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';

if (is_logged_in()) redirect('index.php');

$pdo = db();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');

  $email = trim((string)($_POST['email'] ?? ''));

  if ($email === '') {
    $errors[] = 'El email es obligatorio.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El email no es válido.';
  } else {
    // Buscar usuario
    $st = $pdo->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
      // Puedes dejarlo así (explícito) o hacerlo "silencioso" para no filtrar existencia de usuarios
      $errors[] = 'No existe un usuario registrado con ese email.';
    } else {
      // Generar token
      $token = generate_token(32); // helpers.php
      $tokenHash = hash('sha256', $token); // guardamos hash
      $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

      try {
        // Opcional: invalidar tokens anteriores no usados
        $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
            ->execute([(int)$user['id']]);

        // Guardar token hash
        $st2 = $pdo->prepare("
          INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at)
          VALUES (?, ?, ?, NOW())
        ");
        $st2->execute([(int)$user['id'], $tokenHash, $expires]);

        // En un MVP, puedes mostrar link directo en pantalla (solo dev)
        $resetLink = base_url() . "/reset_password.php?token=" . urlencode($token);

        $success = "Listo. Si el correo existe, recibirás instrucciones para recuperar tu contraseña.";

        // Si quieres debug en local:
        if (($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || str_contains($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1')) {
          $success .= "<br><br><span class='small'>DEV LINK:</span> <a href='".h($resetLink)."'>".h($resetLink)."</a>";
        }
      } catch (Throwable $e) {
        $errors[] = "Error al procesar la solicitud: " . $e->getMessage();
      }
    }
  }
}

require __DIR__ . '/partials/header.php';
?>

<div class="card" style="max-width:560px;margin:20px auto;">
  <h2>Recuperar contraseña</h2>
  <div class="small" style="margin-top:6px">
    Ingresa tu email y te enviaremos un enlace para restablecer tu contraseña.
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert danger" style="margin-top:12px">
      <ul style="margin:0; padding-left:18px">
        <?php foreach ($errors as $er): ?>
          <li><?= h($er) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert ok" style="margin-top:12px">
      <p style="margin:0"><?= $success /* intencional: puede incluir link dev */ ?></p>
    </div>
  <?php endif; ?>

  <form method="post" class="card" style="margin-top:12px">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="field">
      <label>Email</label>
      <input
        type="email"
        name="email"
        required
        value="<?= h($_POST['email'] ?? '') ?>"
        placeholder="tu@correo.com"
        autocomplete="email"
      />
    </div>

    <div style="display:flex; gap:10px; margin-top:10px; flex-wrap:wrap">
      <button class="btn" type="submit">Enviar instrucciones</button>
      <a class="btn secondary" href="login.php">Volver al login</a>
    </div>
  </form>

  <div class="small" style="margin-top:10px">
    ¿No tienes cuenta? <a href="register.php">Crear cuenta</a>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
