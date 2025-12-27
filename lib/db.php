<?php
// lib/db.php
function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $cfg = require __DIR__ . '/../config.php';
  $d = $cfg['db'];
  $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset={$d['charset']}";

  $pdo = new PDO($dsn, $d['user'], $d['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}
