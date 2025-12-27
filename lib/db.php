<?php
// lib/db.php
declare(strict_types=1);

/**
 * Conexión PDO (singleton)
 * Uso:
 *   $pdo = db();
 */
function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  // Ajusta aquí según tu entorno
  $host = 'localhost';
  $dbname = 'conta_mvp';
  $user = 'root';
  $pass = '';

  $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

  $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];

  $pdo = new PDO($dsn, $user, $pass, $options);
  return $pdo;
}
