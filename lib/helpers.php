<?php
// lib/helpers.php
session_start();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $path) {
  header("Location: $path");
  exit;
}

function flash_set(string $type, string $msg) {
  $_SESSION['flash'] = ['type'=>$type, 'msg'=>$msg];
}

function flash_get() {
  $f = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $f;
}

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}

function csrf_check($token): void {
  if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$token)) {
    http_response_code(400);
    die("CSRF inválido.");
  }
}

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
