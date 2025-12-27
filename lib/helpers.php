<?php
// lib/helpers.php
// Helpers generales + Auth + CSRF + Flash + Multiempresa + CLP

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/db.php'; // Debe existir db(): PDO

/* =========================
   Helpers básicos
========================= */
function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
  header("Location: $path");
  exit;
}

/* =========================
   Flash messages (compat + por clave)
   - Compat anterior: flash_set(type,msg) + flash_get() => ['type','msg']
   - Nuevo: flash_set('ok','...'), flash_get('ok')
========================= */
function flash_set(string $keyOrType, string $msg): void {
  // Guardamos en modo "nuevo" por clave:
  $_SESSION['flash_map'][$keyOrType] = $msg;

  // También mantenemos tu formato anterior (type/msg) por compatibilidad:
  $_SESSION['flash'] = ['type' => $keyOrType, 'msg' => $msg];
}

function flash_get(?string $key = null) {
  // Modo nuevo: por clave
  if ($key !== null) {
    if (!isset($_SESSION['flash_map'][$key])) return null;
    $v = $_SESSION['flash_map'][$key];
    unset($_SESSION['flash_map'][$key]);
    return $v;
  }

  // Modo anterior: retorna array ['type','msg']
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $f;
}

/* =========================
   CSRF
========================= */
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['csrf'];
}

function csrf_check($token): void {
  if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$token)) {
    http_response_code(400);
    die("CSRF inválido.");
  }
}

/* =========================
   Rango de fechas por shortcut
========================= */
function date_range_from_shortcut(string $shortcut): array {
  $today = new DateTime('today');

  if ($shortcut === 'week') {
    $start = (clone $today)->modify('monday this week');
    $end = (clone $start)->modify('+6 days');
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
  }

  if ($shortcut === 'month') {
    $start = new DateTime(date('Y-m-01'));
    $end = (clone $start)->modify('last day of this month');
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
  }

  return ['', ''];
}

/* =========================
   Auth (manteniendo tu lógica actual)
========================= */
function is_logged_in(): bool {
  return !empty($_SESSION['user']);
}

function require_login(): void {
  $public = ['login.php', 'setup_admin.php'];
  $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
  if (in_array($current, $public, true)) return;

  if (!is_logged_in()) {
    redirect('login.php');
  }
}

function current_user(): ?array {
  return $_SESSION['user'] ?? null;
}

function login_user(int $id, string $email, string $name): void {
  $_SESSION['user'] = ['id' => $id, 'email' => $email, 'name' => $name];
}

function logout_user(): void {
  unset($_SESSION['user']);
  unset($_SESSION['company_id']); // al salir, limpiamos empresa activa también
}

/* =========================
   Multiempresa
========================= */
function current_company_id(): int {
  return (int)($_SESSION['company_id'] ?? 0);
}

function set_company_id(int $companyId): void {
  $_SESSION['company_id'] = $companyId;
}

/**
 * Verifica si un usuario tiene acceso a una empresa
 * Requiere tabla user_companies(user_id, company_id)
 */
function user_has_company(int $userId, int $companyId): bool {
  $pdo = db();
  $st = $pdo->prepare("SELECT 1 FROM user_companies WHERE user_id=? AND company_id=? LIMIT 1");
  $st->execute([$userId, $companyId]);
  return (bool)$st->fetchColumn();
}

/**
 * Asegura que exista una empresa seleccionada en sesión
 * Si no hay, selecciona automáticamente la primera empresa del usuario
 * Si no tiene empresas, lo manda a companies.php
 */
function require_company_selected(): void {
  require_login();
  $u = current_user();
  if (!$u) redirect('login.php');

  $userId = (int)$u['id'];
  $cid = current_company_id();

  // Si ya hay empresa y el usuario tiene acceso, ok
  if ($cid > 0 && user_has_company($userId, $cid)) return;

  // Buscar primera empresa del usuario
  $pdo = db();
  $st = $pdo->prepare("
    SELECT c.id
    FROM companies c
    JOIN user_companies uc ON uc.company_id = c.id
    WHERE uc.user_id = ?
    ORDER BY c.id ASC
    LIMIT 1
  ");
  $st->execute([$userId]);
  $first = (int)($st->fetchColumn() ?: 0);

  if ($first > 0) {
    set_company_id($first);
    return;
  }

  // No tiene empresas
  flash_set('err', 'No tienes empresas creadas. Crea una para comenzar.');
  redirect('companies.php');
}

/**
 * Fuerza acceso a la empresa indicada (por seguridad si recibes company_id por URL/POST)
 */
function require_company_access(int $companyId): void {
  require_login();
  $u = current_user();
  if (!$u) redirect('login.php');

  if ($companyId <= 0 || !user_has_company((int)$u['id'], $companyId)) {
    flash_set('err', 'No tienes acceso a esa empresa.');
    redirect('companies.php');
  }
}

/**
 * Helper opcional para queries: te devuelve "company_id = ?"
 * Útil para armar where de forma consistente.
 */
function company_where(): string {
  return "company_id = " . (int)current_company_id();
}

/* =========================
   CLP sin decimales
========================= */
function clp($n): string {
  // CLP real: sin decimales
  return '$' . number_format((float)$n, 0, ',', '.');
}
