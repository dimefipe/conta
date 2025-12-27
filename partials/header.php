<?php
require_once __DIR__ . '/../lib/helpers.php';

$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$public = ['login.php','setup_admin.php'];

require_login();

$logged = is_logged_in();
if ($logged && !in_array($script, $public, true)) {
  require_company_selected();
}

$flash = flash_get(); // compat: ['type','msg']

$user = $logged ? current_user() : null;
$companies = [];
$currentCid = 0;

if ($logged) {
  $pdo = db();
  $currentCid = current_company_id();

  $st = $pdo->prepare("
    SELECT c.id, c.name
    FROM companies c
    JOIN user_companies uc ON uc.company_id = c.id
    WHERE uc.user_id = ?
    ORDER BY c.name ASC, c.id ASC
  ");
  $st->execute([(int)$user['id']]);
  $companies = $st->fetchAll();
}

$nav = [
  ['label'=>'Dashboard',      'href'=>'index.php'],
  ['label'=>'Empresas',       'href'=>'companies.php'],
  ['label'=>'Plan de cuentas','href'=>'accounts.php'],
  ['label'=>'Libro diario',   'href'=>'entries.php'],
  ['label'=>'Libro mayor',    'href'=>'ledger.php'],
  ['label'=>'EE.RR.',         'href'=>'reports_is.php'],
  ['label'=>'Balance',        'href'=>'reports_bs.php'],
];

function is_active_nav(string $href): bool {
  $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
  return $current === basename($href);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Contabilidad MVP</title>
  <link rel="stylesheet" href="assets/style.css?v=1" />
</head>
<body>

<?php if ($logged && !in_array($script, $public, true)): ?>
  <header class="topbar">
    <div class="topbar-left">
      <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú" type="button">☰</button>
      <div class="brand">
        <div class="brand-title">Contabilidad MVP</div>
        <div class="brand-sub">Empresa: <b><?= h($companies ? ($companies[array_search($currentCid, array_column($companies,'id'))]['name'] ?? '—') : '—') ?></b></div>
      </div>
    </div>

    <div class="topbar-right">
      <?php if (!empty($companies)): ?>
        <form method="post" action="switch_company.php" class="company-switch">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <select name="company_id" onchange="this.form.submit()">
            <?php foreach ($companies as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === (int)$currentCid) ? 'selected' : '' ?>>
                <?= h($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>

      <div class="userbox">
        <span class="user-name"><?= h($user['name'] ?? '') ?></span>
        <a class="btn secondary" href="logout.php">Salir</a>
      </div>
    </div>
  </header>

  <div class="overlay" id="overlay"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-title">Menú</div>
    <nav class="nav">
      <?php foreach ($nav as $item): ?>
        <a class="<?= is_active_nav($item['href']) ? 'active' : '' ?>" href="<?= h($item['href']) ?>">
          <?= h($item['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>

  <main class="main">
    <?php if ($flash && !empty($flash['msg'])): ?>
      <div class="alert <?= h($flash['type'] ?? 'ok') ?>">
        <?= h($flash['msg']) ?>
      </div>
    <?php endif; ?>

<?php else: ?>
  <!-- Layout simple para login/setup -->
  <div class="public-wrap">
    <?php if ($flash && !empty($flash['msg'])): ?>
      <div class="alert <?= h($flash['type'] ?? 'ok') ?>" style="max-width:720px;margin:16px auto;">
        <?= h($flash['msg']) ?>
      </div>
    <?php endif; ?>
<?php endif; ?>
