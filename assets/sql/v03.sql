/* =========================================================
   CONTA_MVP - Full Install/Upgrade Safe Script (v03-fixed)
   - NO DROP TABLES
   - NO PROCEDURE / NO CALL  (phpMyAdmin friendly)
   - Keeps v02/v03 features + fixes:
     ✅ Companies fields: razon_social, giro, direccion
     ✅ Password reset tokens PRO: token_hash + used_at + fixed expires_at
     ✅ Templates module: entry_templates + entry_template_lines
     ✅ Journal validation fix:
        - Add status DRAFT/POSTED/VOID
        - Drop bad per-line balance trigger(s)
        - Validate ONLY when status changes to POSTED
========================================================= */

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

/* -----------------------
   1) Core tables (safe)
----------------------- */

CREATE TABLE IF NOT EXISTS companies (
  id INT(11) NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  rut VARCHAR(20) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_company_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT(11) NOT NULL AUTO_INCREMENT,
  email VARCHAR(120) NOT NULL,
  name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_companies (
  user_id INT(11) NOT NULL,
  company_id INT(11) NOT NULL,
  role ENUM('OWNER','ADMIN','VIEWER') NOT NULL DEFAULT 'OWNER',
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (user_id, company_id),
  KEY fk_uc_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accounts (
  id INT(11) NOT NULL AUTO_INCREMENT,
  company_id INT(11) NOT NULL,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(120) NOT NULL,
  type ENUM('ASSET','LIABILITY','EQUITY','INCOME','COST','EXPENSE') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_company_code (company_id, code),
  KEY idx_accounts_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_entries (
  id INT(11) NOT NULL AUTO_INCREMENT,
  company_id INT(11) NOT NULL,
  entry_date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  status ENUM('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  created_at DATETIME NOT NULL DEFAULT current_timestamp(),
  voided_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_entries_company_date (company_id, entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS journal_lines (
  id INT(11) NOT NULL AUTO_INCREMENT,
  entry_id INT(11) NOT NULL,
  line_no INT(11) NOT NULL,
  account_id INT(11) NOT NULL,
  memo VARCHAR(255) DEFAULT NULL,
  debit DECIMAL(14,0) NOT NULL DEFAULT 0,
  credit DECIMAL(14,0) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_entry_line (entry_id, line_no),
  KEY idx_lines_entry (entry_id),
  KEY idx_lines_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_prt_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* -----------------------
   1.1) Templates module tables
----------------------- */

CREATE TABLE IF NOT EXISTS entry_templates (
  id INT(11) NOT NULL AUTO_INCREMENT,
  company_id INT(11) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_by INT(11) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT current_timestamp(),
  updated_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_et_company (company_id),
  KEY idx_et_name (company_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS entry_template_lines (
  id INT(11) NOT NULL AUTO_INCREMENT,
  template_id INT(11) NOT NULL,
  sort_order INT(11) NOT NULL DEFAULT 1,
  account_id INT(11) NOT NULL,
  memo VARCHAR(255) DEFAULT NULL,
  debit DECIMAL(14,0) NOT NULL DEFAULT 0,
  credit DECIMAL(14,0) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_etl_template (template_id),
  KEY idx_etl_account (account_id),
  UNIQUE KEY uq_etl_template_sort (template_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


/* -----------------------
   2) Companies fields upgrade (NO PROCEDURE)
----------------------- */

-- razon_social
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='companies' AND COLUMN_NAME='razon_social'
);
SET @sql := IF(@col_exists=0,
  'ALTER TABLE companies ADD COLUMN razon_social VARCHAR(180) NULL AFTER name',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- giro
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='companies' AND COLUMN_NAME='giro'
);
SET @sql := IF(@col_exists=0,
  'ALTER TABLE companies ADD COLUMN giro VARCHAR(180) NULL AFTER rut',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- direccion
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='companies' AND COLUMN_NAME='direccion'
);
SET @sql := IF(@col_exists=0,
  'ALTER TABLE companies ADD COLUMN direccion VARCHAR(220) NULL AFTER giro',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- normaliza rut vacío a NULL para evitar choque con UNIQUE
UPDATE companies SET rut = NULL WHERE rut = '';

-- unique rut (solo si no existe y no hay duplicados)
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='companies' AND INDEX_NAME='uq_companies_rut'
);
SET @dup_count := (
  SELECT COUNT(*) FROM (
    SELECT rut FROM companies
    WHERE rut IS NOT NULL AND rut <> ''
    GROUP BY rut HAVING COUNT(*) > 1
  ) t
);
SET @sql := IF(@idx_exists=0 AND @dup_count=0,
  'ALTER TABLE companies ADD UNIQUE KEY uq_companies_rut (rut)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


/* -----------------------
   3) Password reset tokens PRO (NO PROCEDURE)
----------------------- */

-- Fix expires_at (quita ON UPDATE si existía)
ALTER TABLE password_reset_tokens MODIFY expires_at TIMESTAMP NOT NULL;

-- token_hash
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='password_reset_tokens' AND COLUMN_NAME='token_hash'
);
SET @sql := IF(@col_exists=0,
  'ALTER TABLE password_reset_tokens ADD COLUMN token_hash CHAR(64) NULL AFTER user_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- used_at
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='password_reset_tokens' AND COLUMN_NAME='used_at'
);
SET @sql := IF(@col_exists=0,
  'ALTER TABLE password_reset_tokens ADD COLUMN used_at TIMESTAMP NULL DEFAULT NULL AFTER expires_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill token_hash
UPDATE password_reset_tokens
   SET token_hash = SHA2(token, 256)
 WHERE (token_hash IS NULL OR token_hash = '')
   AND token IS NOT NULL AND token <> '';

UPDATE password_reset_tokens
   SET token_hash = SHA2(CONCAT("legacy-", id, "-", RAND()), 256)
 WHERE (token_hash IS NULL OR token_hash = '');

ALTER TABLE password_reset_tokens MODIFY token_hash CHAR(64) NOT NULL;

-- Indexes token_hash / expires_at / user_id
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='password_reset_tokens' AND INDEX_NAME='idx_prt_token_hash'
);
SET @sql := IF(@idx_exists=0,
  'CREATE INDEX idx_prt_token_hash ON password_reset_tokens(token_hash)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='password_reset_tokens' AND INDEX_NAME='idx_prt_expires_at'
);
SET @sql := IF(@idx_exists=0,
  'CREATE INDEX idx_prt_expires_at ON password_reset_tokens(expires_at)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- (idx_prt_user_id ya viene en CREATE TABLE, pero por si acaso)
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='password_reset_tokens' AND INDEX_NAME='idx_prt_user_id'
);
SET @sql := IF(@idx_exists=0,
  'CREATE INDEX idx_prt_user_id ON password_reset_tokens(user_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


/* -----------------------
   4) Ensure FOREIGN KEYS safely (NO PROCEDURE)
----------------------- */

-- user_companies -> users
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME='user_companies' AND CONSTRAINT_NAME='fk_uc_user'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE user_companies ADD CONSTRAINT fk_uc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- user_companies -> companies
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME='user_companies' AND CONSTRAINT_NAME='fk_uc_company'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE user_companies ADD CONSTRAINT fk_uc_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- accounts -> companies
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME='accounts' AND CONSTRAINT_NAME='fk_accounts_company'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE accounts ADD CONSTRAINT fk_accounts_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- journal_entries -> companies
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME='journal_entries' AND CONSTRAINT_NAME='fk_entries_company'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE journal_entries ADD CONSTRAINT fk_entries_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- journal_lines -> journal_entries
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME='journal_lines' AND CONSTRAINT_NAME='fk_lines_entry'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE journal_lines ADD CONSTRAINT fk_lines_entry FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- journal_lines -> accounts
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME='journal_lines' AND CONSTRAINT_NAME='fk_lines_account'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE journal_lines ADD CONSTRAINT fk_lines_account FOREIGN KEY (account_id) REFERENCES accounts(id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- password_reset_tokens -> users
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME='password_reset_tokens' AND CONSTRAINT_NAME='password_reset_tokens_ibfk_1'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE password_reset_tokens ADD CONSTRAINT password_reset_tokens_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- entry_templates -> companies
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME='entry_templates' AND CONSTRAINT_NAME='fk_et_company'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE entry_templates ADD CONSTRAINT fk_et_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- entry_templates -> users (created_by)
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME='entry_templates' AND CONSTRAINT_NAME='fk_et_created_by'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE entry_templates ADD CONSTRAINT fk_et_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- entry_template_lines -> entry_templates
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME='entry_template_lines' AND CONSTRAINT_NAME='fk_etl_template'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE entry_template_lines ADD CONSTRAINT fk_etl_template FOREIGN KEY (template_id) REFERENCES entry_templates(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- entry_template_lines -> accounts
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME='entry_template_lines' AND CONSTRAINT_NAME='fk_etl_account'
);
SET @sql := IF(@fk_exists=0,
  'ALTER TABLE entry_template_lines ADD CONSTRAINT fk_etl_account FOREIGN KEY (account_id) REFERENCES accounts(id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


/* -----------------------
   5) ✅ Journal validation FIX (NO PROCEDURE)
----------------------- */

-- Asegura enum con DRAFT (sin romper tu flujo actual: default POSTED)
ALTER TABLE journal_entries
  MODIFY status ENUM('DRAFT','POSTED','VOID') NOT NULL DEFAULT 'POSTED';

-- Drop triggers malos conocidos (el que viste en tu captura)
DROP TRIGGER IF EXISTS trg_entry_balance_check_ai;

-- (por si existen variantes)
DROP TRIGGER IF EXISTS trg_entry_balance_check_au;
DROP TRIGGER IF EXISTS trg_entry_balance_check_bi;
DROP TRIGGER IF EXISTS trg_entry_balance_check_bu;

-- Re-crea trigger correcto (solo valida al pasar a POSTED)
DROP TRIGGER IF EXISTS trg_journal_entries_validate_posted;

DELIMITER $$

CREATE TRIGGER trg_journal_entries_validate_posted
BEFORE UPDATE ON journal_entries
FOR EACH ROW
BEGIN
  DECLARE sd DECIMAL(14,0) DEFAULT 0;
  DECLARE sc DECIMAL(14,0) DEFAULT 0;

  IF NEW.status = 'POSTED' AND OLD.status <> 'POSTED' THEN

    SELECT COALESCE(SUM(debit),0), COALESCE(SUM(credit),0)
      INTO sd, sc
    FROM journal_lines
    WHERE entry_id = NEW.id;

    IF sd <> sc OR sd = 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Asiento descuadrado: Debe y Haber deben ser iguales';
    END IF;
  END IF;
END$$

DELIMITER ;


/* -----------------------
   6) Seeds (idempotent)
----------------------- */

INSERT INTO companies (id, name, razon_social, rut, giro, direccion)
VALUES
  (1, 'Empresa A', 'Empresa A', NULL, 'Servicios', ''),
  (2, 'Fipe - The Hybrid Mind Spa', 'Fipe - The Hybrid Mind Spa', '77.270.095-5', 'Diseño / Publicidad / Marketing', '')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  razon_social = VALUES(razon_social),
  rut = VALUES(rut),
  giro = VALUES(giro),
  direccion = VALUES(direccion);

INSERT IGNORE INTO users (id, email, name, password_hash, created_at)
VALUES
  (1, 'dimefipe@gmail.com', 'Fipe', '$2y$10$EoiN.wwVOj1APO0RHkKe2e.EmLYifywgewGYMQF7itksJCmTuIB4.', '2025-12-27 13:00:01');

INSERT IGNORE INTO user_companies (user_id, company_id, role)
VALUES
  (1, 1, 'OWNER'),
  (1, 2, 'OWNER');

INSERT INTO accounts (company_id, code, name, type, is_active)
VALUES
  (1, '1101', 'Caja', 'ASSET', 1),
  (1, '1102', 'Banco', 'ASSET', 1),
  (1, '1103', 'Clientes por cobrar (CxC)', 'ASSET', 1),
  (1, '1104', 'IVA crédito fiscal', 'ASSET', 1),
  (1, '2101', 'Proveedores por pagar (CxP)', 'LIABILITY', 1),
  (1, '2102', 'IVA débito fiscal', 'LIABILITY', 1),
  (1, '2103', 'Impuestos por pagar', 'LIABILITY', 1),
  (1, '3101', 'Capital', 'EQUITY', 1),
  (1, '3102', 'Resultado acumulado', 'EQUITY', 1),
  (1, '4101', 'Ingresos - Marketing / RRSS', 'INCOME', 1),
  (1, '4102', 'Ingresos - Branding', 'INCOME', 1),
  (1, '4103', 'Ingresos - Desarrollo Web', 'INCOME', 1),
  (1, '4104', 'Ingresos - Producción Audiovisual', 'INCOME', 1),
  (1, '4105', 'Ingresos - Consultorías', 'INCOME', 1),
  (1, '5101', 'Costos - Freelancers / Producción', 'COST', 1),
  (1, '5102', 'Costos - Subcontratos', 'COST', 1),
  (1, '6101', 'Gastos - Software / Suscripciones', 'EXPENSE', 1),
  (1, '6102', 'Gastos - Publicidad (propia)', 'EXPENSE', 1),
  (1, '6103', 'Gastos - Arriendo / Cowork', 'EXPENSE', 1),
  (1, '6104', 'Gastos - Internet / Telefonía', 'EXPENSE', 1),
  (1, '6105', 'Gastos - Transporte', 'EXPENSE', 1),
  (1, '6106', 'Gastos - Contabilidad / Legal', 'EXPENSE', 1),
  (1, '6107', 'Gastos - Bancarios', 'EXPENSE', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  type = VALUES(type),
  is_active = VALUES(is_active);
