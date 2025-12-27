<?php
// reset_password.php - Página para restablecer contraseña
require_once 'lib/helpers.php';
require_once 'lib/db.php';

// Si el usuario ya está logueado, redirigir a la página principal
if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$success = '';
$token = $_GET['token'] ?? '';

if ($token) {
    // Verificar que el token sea válido y no haya expirado
    $stmt = $db->prepare("SELECT user_id FROM password_reset_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$token_data) {
        $errors[] = "El token de recuperación no es válido o ha expirado.";
    }
}

if ($_POST && $token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validaciones
    if (empty($password)) {
        $errors[] = "La contraseña es obligatoria";
    } elseif (strlen($password) < 6) {
        $errors[] = "La contraseña debe tener al menos 6 caracteres";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Las contraseñas no coinciden";
    }
    
    if (empty($errors)) {
        try {
            // Actualizar la contraseña del usuario
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $token_data['user_id']]);
            
            // Eliminar el token usado
            $stmt = $db->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
            $stmt->execute([$token]);
            
            $success = "Contraseña actualizada correctamente. Puedes iniciar sesión ahora.";
        } catch (Exception $e) {
            $errors[] = "Error al actualizar la contraseña: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Restablecer Contraseña</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h1>Restablecer Contraseña</h1>
        
        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($token && !$success): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="password">Nueva Contraseña:</label>
                    <input type="password" id="password" name="password" data-pw-toggle="1" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmar Contraseña:</label>
                    <input type="password"  id="confirm_password" name="confirm_password" data-pw-toggle="1" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Restablecer Contraseña</button>
                <a href="login.php" class="btn btn-secondary">Volver al Inicio de Sesión</a>
            </form>
        <?php else: ?>
            <p>Token inválido o expirado.</p>
            <a href="forgot_password.php" class="btn btn-secondary">Solicitar nuevo token</a>
        <?php endif; ?>
    </div>
</body>
</html>