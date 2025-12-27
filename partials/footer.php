<?php
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');

$public = [
  'login.php',
  'setup_admin.php',
  'register.php',
  'forgot_password.php',
  'reset_password.php',
];
?>
<?php if (is_logged_in() && !in_array($script, $public, true)): ?>
  </main>
<?php else: ?>
  </div>
<?php endif; ?>

<script src="assets/app.js?v=2"></script>
</body>
</html>
