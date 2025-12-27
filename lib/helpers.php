<?php
// lib/helpers.php
// Helpers generales + Auth + CSRF + Flash + Multiempresa + CLP
// Fuente única de verdad para sesión/auth: $_SESSION['user'] (id,email,name)

declare(strict_types=1);

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
  $path = str_replace(["\r", "\n"], '', $path);
  header("Location: $path");
  exit;
}

function get_str(string $key, string $default = ''): string {
  $v = $_GET[$key] ?? $default;
  return is_string($v) ? trim($v) : $default;
}

/* =========================
   Flash messages (por clave)
========================= */
function flash_set(string $keyOrType, string $msg): void {
  $_SESSION['flash_map'][$keyOrType] = $msg;
  // compat antigua
  $_SESSION['flash'] = ['type' => $keyOrType, 'msg' => $msg];
}

function flash_get(?string $key = null) {
  if ($key !== null) {
    if (!isset($_SESSION['flash_map'][$key])) return null;
    $v = $_SESSION['flash_map'][$key];
    unset($_SESSION['flash_map'][$key]);
    return $v;
  }

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
   Fechas / Rangos (para filtros rápidos)
   - is_valid_date('Y-m-d')
   - ym_range('YYYY-MM')
   - date_range_from_shortcut('week'|'month'|'last_month'|'last_3_months')
========================= */
function is_valid_date(string $s): bool {
  if ($s === '') return false;
  $d = DateTime::createFromFormat('Y-m-d', $s);
  return $d !== false && $d->format('Y-m-d') === $s;
}

function is_valid_ym(string $ym): bool {
  // YYYY-MM
  return (bool)preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $ym);
}

function last_day_of_month(int $year, int $month): string {
  $dt = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month));
  $dt->modify('last day of this month');
  return $dt->format('Y-m-d');
}

function ym_range(string $ym): array {
  if (!is_valid_ym($ym)) return ['', ''];
  $year = (int)substr($ym, 0, 4);
  $month = (int)substr($ym, 5, 2);
  $from = sprintf('%04d-%02d-01', $year, $month);
  $to   = last_day_of_month($year, $month);
  return [$from, $to];
}

function date_range_from_shortcut(string $shortcut): array {
  $today = new DateTime('today');

  if ($shortcut === 'week') {
    $start = (clone $today)->modify('monday this week');
    $end   = (clone $start)->modify('+6 days');
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
  }

  if ($shortcut === 'month') {
    $start = new DateTime(date('Y-m-01'));
    $end   = (clone $start)->modify('last day of this month');
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
  }

  if ($shortcut === 'last_month') {
    $start = (new DateTime('first day of last month'))->setTime(0,0,0);
    $end   = (new DateTime('last day of last month'))->setTime(0,0,0);
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
  }

  if ($shortcut === 'last_3_months') {
    // desde el 1° del mes (hace 2 meses) hasta fin del mes actual
    $start = (new DateTime('first day of -2 months'))->setTime(0,0,0);
    $end   = (new DateTime('last day of this month'))->setTime(0,0,0);
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
  }

  return ['', ''];
}

/**
 * Resuelve filtros desde GET:
 * - prioridad 1: ym=YYYY-MM
 * - prioridad 2: range=week|month|last_month|last_3_months
 * - prioridad 3: from=Y-m-d & to=Y-m-d
 */
function resolve_date_filters(): array {
  $ym = get_str('ym');
  if ($ym !== '') {
    [$from, $to] = ym_range($ym);
    if ($from !== '' && $to !== '') return ['from' => $from, 'to' => $to, 'ym' => $ym, 'range' => ''];
  }

  $range = get_str('range');
  if ($range !== '') {
    [$from, $to] = date_range_from_shortcut($range);
    if ($from !== '' && $to !== '') return ['from' => $from, 'to' => $to, 'ym' => '', 'range' => $range];
  }

  $from = get_str('from');
  $to   = get_str('to');
  if (is_valid_date($from) && is_valid_date($to)) {
    return ['from' => $from, 'to' => $to, 'ym' => '', 'range' => ''];
  }

  return ['from' => '', 'to' => '', 'ym' => '', 'range' => ''];
}

/* =========================
   Auth (sesión estándar)
========================= */
function is_logged_in(): bool {
  return !empty($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function current_user(): ?array {
  return $_SESSION['user'] ?? null;
}

function require_login(): void {
  $public = [
    'login.php',
    'setup_admin.php',
    'register.php',
    'forgot_password.php',
    'reset_password.php',
  ];

  $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
  if (in_array($current, $public, true)) return;

  if (!is_logged_in()) redirect('login.php');
}

function login_user(int $id, string $email, string $name): void {
  // Seguridad: evita session fixation
  session_regenerate_id(true);

  $_SESSION['user'] = [
    'id' => $id,
    'email' => $email,
    'name' => $name,
  ];
}

function logout_user(): void {
  unset($_SESSION['user']);
  unset($_SESSION['company_id']);
  unset($_SESSION['csrf']);
  session_regenerate_id(true);
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

function user_has_company(int $userId, int $companyId): bool {
  if ($userId <= 0 || $companyId <= 0) return false;

  $pdo = db();
  $st = $pdo->prepare("SELECT 1 FROM user_companies WHERE user_id=? AND company_id=? LIMIT 1");
  $st->execute([$userId, $companyId]);
  return (bool)$st->fetchColumn();
}

function require_company_selected(): void {
  require_login();
  $u = current_user();
  if (!$u) redirect('login.php');

  $userId = (int)$u['id'];
  $cid = current_company_id();

  if ($cid > 0 && user_has_company($userId, $cid)) return;

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

  flash_set('err', 'No tienes empresas creadas. Crea una para comenzar.');
  redirect('companies.php');
}

function require_company_access(int $companyId): void {
  require_login();
  $u = current_user();
  if (!$u) redirect('login.php');

  if ($companyId <= 0 || !user_has_company((int)$u['id'], $companyId)) {
    flash_set('err', 'No tienes acceso a esa empresa.');
    redirect('companies.php');
  }
}

function company_where(): string {
  return "company_id = " . (int)current_company_id();
}

/* =========================
   CLP sin decimales
========================= */
function clp($n): string {
  return '$' . number_format((float)$n, 0, ',', '.');
}

/* =========================
   Utilidades
========================= */
function validate_email(string $email): bool {
  return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_password(string $password, int $minLen = 6): bool {
  return mb_strlen($password) >= $minLen;
}

function generate_token(int $bytes = 32): string {
  return bin2hex(random_bytes($bytes));
}

function base_url(): string {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  $scheme = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme . '://' . $host;
}

function current_url(): string {
  $uri = $_SERVER['REQUEST_URI'] ?? '/';
  return base_url() . $uri;
}
