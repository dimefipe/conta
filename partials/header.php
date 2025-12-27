<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

$script = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Páginas públicas (sin topbar/sidebar, no requieren login)
$public = [
  'login.php',
  'setup_admin.php',
  'register.php',
  'forgot_password.php',
  'reset_password.php',
];

// Páginas logueadas pero NO requieren empresa seleccionada
$noCompanyRequired = [
  'companies.php',
  'switch_company.php',
  'logout.php',
  'user_profile.php',
];

// Si NO es pública => requiere login
if (!in_array($script, $public, true)) {
  require_login();
}

$logged = is_logged_in();

// Solo exigir empresa si está logueado y no es pública ni "noCompanyRequired"
if ($logged && !in_array($script, $public, true) && !in_array($script, $noCompanyRequired, true)) {
  require_company_selected();
}

$flash = flash_get();

$user = $logged ? current_user() : null;

$companies = [];
$currentCid = $logged ? current_company_id() : 0;
$currentCompanyName = '—';

// Cargar empresas del usuario (si está logueado)
if ($logged) {
  $pdo = db();

  $st = $pdo->prepare("
    SELECT c.id, c.name
    FROM companies c
    JOIN user_companies uc ON uc.company_id = c.id
    WHERE uc.user_id = ?
    ORDER BY c.name ASC, c.id ASC
  ");
  $st->execute([(int)($user['id'] ?? 0)]);
  $companies = $st->fetchAll() ?: [];

  foreach ($companies as $c) {
    if ((int)$c['id'] === (int)$currentCid) {
      $currentCompanyName = (string)$c['name'];
      break;
    }
  }
  if ($currentCompanyName === '—' && !empty($companies)) {
    $currentCompanyName = (string)$companies[0]['name'];
  }
}

// Helpers active/open
function current_script(): string {
  return basename($_SERVER['SCRIPT_NAME'] ?? '');
}
function is_active_href(string $href): bool {
  return current_script() === basename($href);
}
function is_active_aliases(array $aliases): bool {
  foreach ($aliases as $alias) {
    if (current_script() === basename((string)$alias)) return true;
  }
  return false;
}
function item_is_active(array $it): bool {
  $href = (string)($it['href'] ?? '');
  if ($href !== '' && $href !== '#' && is_active_href($href)) return true;
  if (!empty($it['aliases']) && is_array($it['aliases']) && is_active_aliases($it['aliases'])) return true;
  return false;
}
function first_enabled_href(array $items): string {
  foreach ($items as $it) {
    if (empty($it['disabled']) && !empty($it['href'])) {
      return (string)$it['href'];
    }
  }
  return '#';
}
function group_is_active(array $items, string $groupHref = ''): bool {
  if ($groupHref !== '' && $groupHref !== '#' && is_active_href($groupHref)) return true;
  foreach ($items as $it) {
    if (item_is_active((array)$it)) return true;
  }
  return false;
}

/**
 * NAV agrupado:
 * - Click en fila (icono + label) => link directo del grupo (groupHref)
 * - Click en flecha => abre/cierra submenu
 * - Para eliminar redundancias: si el primer item del grupo es el mismo href del padre,
 *   NO se renderiza dentro del submenu.
 */
$navGroups = [
  [
    'id' => 'dashboard',
    'label' => 'Dashboard',
    'icon' => '📊',
    'items' => [
      ['label' => 'Resumen', 'href' => 'index.php'],
    ],
  ],
  [
    'id' => 'empresas',
    'label' => 'Empresas',
    'icon' => '🏢',
    'items' => [
      ['label' => 'Administrar empresas', 'href' => 'companies.php'],
    ],
  ],
  [
    'id' => 'cuentas',
    'label' => 'Plan de cuentas',
    'icon' => '🧾',
    'items' => [
      ['label' => 'Ver plan de cuentas', 'href' => 'accounts.php'],
      ['label' => 'Nueva cuenta', 'href' => 'account_new.php', 'disabled' => true],
    ],
  ],
  [
    'id' => 'diario',
    'label' => 'Libro diario',
    'icon' => '📘',
    'items' => [
      ['label' => 'Ver asientos', 'href' => 'entries.php'],
      ['label' => 'Nuevo asiento', 'href' => 'entry_new.php', 'aliases' => ['entry_view.php','entry_edit.php','entry_void.php']],
      ['label' => 'Plantillas de asientos', 'href' => 'entry_templates.php', 'disabled' => true],
    ],
  ],
  [
    'id' => 'mayor',
    'label' => 'Libro mayor',
    'icon' => '📗',
    'items' => [
      ['label' => 'Ver libro mayor', 'href' => 'ledger.php'],
    ],
  ],
  [
    'id' => 'reportes',
    'label' => 'Reportes',
    'icon' => '📑',
    'items' => [
      ['label' => 'EE.RR.', 'href' => 'reports_is.php'],
      ['label' => 'Balance', 'href' => 'reports_bs.php'],
    ],
  ],
  [
    'id' => 'perfil',
    'label' => 'Mi perfil',
    'icon' => '👤',
    'items' => [
      ['label' => 'Editar perfil', 'href' => 'user_profile.php'],
    ],
  ],
];

$supportItem = [
  'label'    => 'Soporte',
  'href'     => 'https://chat.whatsapp.com/DswhlGROm1R2rNOiGDCvHY',
  'external' => true,
  'title'    => 'Canal para ayudar a mejorar el software: reporta errores, propone funciones y deja feedback.',
  'tooltip'  => 'Reporta errores, propone mejoras y deja feedback.',
];

?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Contabilidad MVP</title>
  <link rel="stylesheet" href="assets/style.css?v=2" />
</head>
<body>

<?php if ($logged && !in_array($script, $public, true)): ?>
  <header class="topbar">
    <div class="topbar-left">
      <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú" type="button">☰</button>
      <div class="brand">
        <div class="brand-title">Contabilidad MVP</div>
        <div class="brand-sub">Empresa: <b><?= h($currentCompanyName) ?></b></div>
      </div>
    </div>

    <!-- Desktop controls (en mobile se ocultan y pasan al sidebar) -->
    <div class="topbar-right topbar-desktop">
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
        <a class="chip" href="user_profile.php" title="Editar mi perfil"><?= h($user['name'] ?? '') ?></a>
        <a class="btn secondary" href="logout.php">Salir</a>
      </div>
    </div>
  </header>

  <div class="overlay" id="overlay"></div>

  <aside class="sidebar" id="sidebar" aria-label="Menú lateral">
    <div class="sidebar-head">
      <div class="sidebar-title">Menú</div>

      <!-- Mobile account panel -->
      <div class="sidebar-account">
        <?php if (!empty($companies)): ?>
          <div class="field">
            <label>Empresa</label>
            <form method="post" action="switch_company.php">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <select name="company_id" onchange="this.form.submit()">
                <?php foreach ($companies as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= ((int)$c['id'] === (int)$currentCid) ? 'selected' : '' ?>>
                    <?= h($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        <?php endif; ?>

        <div class="sidebar-userrow">
          <a class="chip grow" href="user_profile.php"><?= h($user['name'] ?? '') ?></a>
          <a class="btn danger smallbtn" href="logout.php">Salir</a>
        </div>
      </div>
    </div>

    <nav class="nav" aria-label="Navegación">
      <?php foreach ($navGroups as $g): ?>
        <?php
          $gid   = (string)($g['id'] ?? '');
          $items = (array)($g['items'] ?? []);

          // href directo del grupo: si no se setea explícito, usa el primer item habilitado
          $groupHref = !empty($g['href']) ? (string)$g['href'] : first_enabled_href($items);

          // Para eliminar redundancia: excluye del submenu el item cuyo href sea igual al del padre
          $children = [];
          foreach ($items as $it) {
            $href = (string)($it['href'] ?? '');
            if ($href !== '' && $href === $groupHref) continue;
            $children[] = $it;
          }

          $hasChildren = count($children) > 0;

          $isGroupActive = group_is_active($items, $groupHref);
          $open = ($hasChildren && $isGroupActive) ? 'open' : '';

          $safeId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $gid);
          $subId = 'nav-sub-' . $safeId;
        ?>
        <section class="nav-group <?= h($open) ?>" data-nav-group="<?= h($gid) ?>">
          <div class="nav-group-row">
            <!-- CLICK EN FILA = ENLACE DIRECTO -->
            <a class="nav-group-link <?= $isGroupActive ? 'active' : '' ?>" href="<?= h($groupHref) ?>">
              <span class="nav-group-left">
                <span class="nav-ico" aria-hidden="true"><?= h((string)$g['icon']) ?></span>
                <span class="nav-group-title"><?= h((string)$g['label']) ?></span>
              </span>
            </a>

            <!-- CLICK EN FLECHA = TOGGLE SUBMENU -->
            <?php if ($hasChildren): ?>
              <button
                class="nav-group-toggle"
                data-nav-toggle="1"
                type="button"
                aria-label="Abrir/cerrar submenú"
                aria-expanded="<?= $open ? 'true' : 'false' ?>"
                aria-controls="<?= h($subId) ?>"
              >
                <span class="nav-caret" aria-hidden="true">▾</span>
              </button>
            <?php endif; ?>
          </div>

          <?php if ($hasChildren): ?>
            <div class="nav-sub" id="<?= h($subId) ?>" data-nav-panel="1">
              <?php foreach ($children as $it): ?>
                <?php
                  $href = (string)($it['href'] ?? '#');
                  $disabled = !empty($it['disabled']);
                  $active = (!$disabled && item_is_active((array)$it)) ? 'active' : '';
                ?>
                <a
                  class="nav-item <?= h($active) ?> <?= $disabled ? 'disabled' : '' ?>"
                  href="<?= $disabled ? '#' : h($href) ?>"
                  <?= $disabled ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                >
                  <?= h((string)$it['label']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>

      <!-- Soporte al final -->
      <a
        class="nav-item nav-external"
        href="<?= h($supportItem['href']) ?>"
        title="<?= h($supportItem['title']) ?>"
        data-tooltip="<?= h($supportItem['tooltip']) ?>"
        target="_blank" rel="noopener noreferrer"
      >
        🛟 <?= h($supportItem['label']) ?> <span class="nav-hint" aria-hidden="true">↗</span>
      </a>
    </nav>
  </aside>

  <main class="main">
    <?php if ($flash && !empty($flash['msg'])): ?>
      <div class="alert <?= h($flash['type'] ?? 'ok') ?>">
        <?= h($flash['msg']) ?>
      </div>
    <?php endif; ?>

<?php else: ?>
  <div class="public-wrap">
    <?php if ($flash && !empty($flash['msg'])): ?>
      <div class="alert <?= h($flash['type'] ?? 'ok') ?>" style="max-width:720px;margin:16px auto;">
        <?= h($flash['msg']) ?>
      </div>
    <?php endif; ?>
<?php endif; ?>
