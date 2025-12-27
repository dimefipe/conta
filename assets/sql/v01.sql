/* =========================================================
   CONTA_MVP - Full Install/Upgrade Safe Script (PRO)
   - No DROP tables
   - Adds companies fields: razon_social, giro, direccion
   - Password reset tokens: token_hash + used_at + fixed expires_at
   - Seeds included (idempotent)
========================================================= */

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

/* -----------------------
   1) Ensure core tables exist
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
   2) Add/ensure FOREIGN KEYS safely where missing
   (We add only if not present)
----------------------- */
DELIMITER $$

CREATE PROCEDURE ensure_fks()
BEGIN
  DECLARE fk_exists INT DEFAULT 0;

  /* user_companies -> users */
  SELECT COUNT(*) INTO fk_exists
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'user_companies'
    AND CONSTRAINT_NAME = 'fk_uc_user';

  IF fk_exists = 0 THEN
    ALTER TABLE user_companies
      ADD CONSTRAINT fk_uc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
  END IF;

  /* user_companies -> companies */
  SELECT COUNT(*) INTO fk_exists
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'user_companies'
    AND CONSTRAINT_NAME = 'fk_uc_company';

  IF fk_exists = 0 THEN
    ALTER TABLE user_companies
      ADD CONSTRAINT fk_uc_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;
  END IF;

  /* accounts -> companies */
  SELECT COUNT(*) INTO fk_exists
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'accounts'
    AND CONSTRAINT_NAME = 'fk_accounts_company';

  IF fk_exists = 0 THEN
    ALTER TABLE accounts
      ADD CONSTRAINT fk_accounts_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;
  END IF;

  /* journal_entries -> companies */
  SELECT COUNT(*) INTO fk_exists
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'journal_entries'
    AND CONSTRAINT_NAME = 'fk_entries_company';

  IF fk_exists = 0 THEN
    ALTER TABLE journal_entries
      ADD CONSTRAINT fk_entries_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;
  END IF;

  /* journal_lines -> journal_entries */
  SELECT COUNT(*) INTO fk_exists
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'journal_lines'
    AND CONSTRAINT_NAME = 'fk_lines_entry';

  IF fk_exists = 0 THEN
    ALTER TABLE journal_lines
      ADD CONSTRAINT fk_lines_entry FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE;
  END IF;

  /* journal_lines -> accounts */
  SELECT COUNT(*) INTO fk_exists
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'journal_lines'
    AND CONSTRAINT_NAME = 'fk_lines_account';

  IF fk_exists = 0 THEN
    ALTER TABLE journal_lines
      ADD CONSTRAINT fk_lines_account FOREIGN KEY (account_id) REFERENCES accounts(id);
  END IF;

  /* password_reset_tokens -> users */
  SELECT COUNT(*) INTO fk_exists
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'password_reset_tokens'
    AND CONSTRAINT_NAME = 'password_reset_tokens_ibfk_1';

  IF fk_exists = 0 THEN
    ALTER TABLE password_reset_tokens
      ADD CONSTRAINT password_reset_tokens_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
  END IF;

END$$
DELIMITER ;

CALL ensure_fks();
DROP PROCEDURE ensure_fks;


/* -----------------------
   3) Companies fields upgrade:
      - razon_social, giro, direccion
      - rut unique (optional but recommended)
----------------------- */
DELIMITER $$

CREATE PROCEDURE migrate_companies_fields()
BEGIN
  DECLARE col_exists INT DEFAULT 0;

  SELECT COUNT(*) INTO col_exists
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'companies'
    AND COLUMN_NAME = 'razon_social';

  IF col_exists = 0 THEN
    ALTER TABLE companies ADD COLUMN razon_social VARCHAR(180) NULL AFTER name;
  END IF;

  SELECT COUNT(*) INTO col_exists
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'companies'
    AND COLUMN_NAME = 'giro';

  IF col_exists = 0 THEN
    ALTER TABLE companies ADD COLUMN giro VARCHAR(180) NULL AFTER rut;
  END IF;

  SELECT COUNT(*) INTO col_exists
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'companies'
    AND COLUMN_NAME = 'direccion';

  IF col_exists = 0 THEN
    ALTER TABLE companies ADD COLUMN direccion VARCHAR(220) NULL AFTER giro;
  END IF;

END$$
DELIMITER ;

CALL migrate_companies_fields();
DROP PROCEDURE migrate_companies_fields;

/* Add unique index for rut if missing (safe attempt) */
DELIMITER $$

CREATE PROCEDURE ensure_companies_rut_unique()
BEGIN
  DECLARE idx_exists INT DEFAULT 0;

  SELECT COUNT(*) INTO idx_exists
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'companies'
    AND INDEX_NAME = 'uq_companies_rut';

  IF idx_exists = 0 THEN
    /* This will fail only if you have duplicate non-empty RUTs */
    ALTER TABLE companies ADD UNIQUE KEY uq_companies_rut (rut);
  END IF;
END$$

DELIMITER ;

CALL ensure_companies_rut_unique();
DROP PROCEDURE ensure_companies_rut_unique;


/* -----------------------
   4) Password reset tokens PRO migration:
      - Fix expires_at (remove ON UPDATE)
      - Add token_hash + used_at
      - Backfill token_hash from legacy token
      - Add indexes
----------------------- */
DELIMITER $$

CREATE PROCEDURE migrate_password_reset_tokens_pro()
BEGIN
  DECLARE col_exists INT DEFAULT 0;
  DECLARE idx_exists INT DEFAULT 0;

  /* Fix expires_at (remove ON UPDATE behavior) */
  ALTER TABLE password_reset_tokens MODIFY expires_at TIMESTAMP NOT NULL;

  /* Add token_hash if missing (nullable for backfill) */
  SELECT COUNT(*) INTO col_exists
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'password_reset_tokens'
    AND COLUMN_NAME = 'token_hash';

  IF col_exists = 0 THEN
    ALTER TABLE password_reset_tokens ADD COLUMN token_hash CHAR(64) NULL AFTER user_id;
  END IF;

  /* Add used_at if missing */
  SELECT COUNT(*) INTO col_exists
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'password_reset_tokens'
    AND COLUMN_NAME = 'used_at';

  IF col_exists = 0 THEN
    ALTER TABLE password_reset_tokens ADD COLUMN used_at TIMESTAMP NULL DEFAULT NULL AFTER expires_at;
  END IF;

  /* Backfill token_hash from legacy token */
  UPDATE password_reset_tokens
     SET token_hash = SHA2(token, 256)
   WHERE (token_hash IS NULL OR token_hash = '')
     AND token IS NOT NULL
     AND token <> '';

  /* Ensure NOT NULL after backfill */
  UPDATE password_reset_tokens
     SET token_hash = SHA2(CONCAT('legacy-', id, '-', RAND()), 256)
   WHERE (token_hash IS NULL OR token_hash = '');

  ALTER TABLE password_reset_tokens MODIFY token_hash CHAR(64) NOT NULL;

  /* Indexes */
  SELECT COUNT(*) INTO idx_exists
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'password_reset_tokens'
    AND INDEX_NAME = 'idx_prt_token_hash';

  IF idx_exists = 0 THEN
    CREATE INDEX idx_prt_token_hash ON password_reset_tokens(token_hash);
  END IF;

  SELECT COUNT(*) INTO idx_exists
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'password_reset_tokens'
    AND INDEX_NAME = 'idx_prt_user_id';

  IF idx_exists = 0 THEN
    CREATE INDEX idx_prt_user_id ON password_reset_tokens(user_id);
  END IF;

  SELECT COUNT(*) INTO idx_exists
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'password_reset_tokens'
    AND INDEX_NAME = 'idx_prt_expires_at';

  IF idx_exists = 0 THEN
    CREATE INDEX idx_prt_expires_at ON password_reset_tokens(expires_at);
  END IF;

END$$
DELIMITER ;

CALL migrate_password_reset_tokens_pro();
DROP PROCEDURE migrate_password_reset_tokens_pro;


/* -----------------------
   5) Seeds (idempotent)
----------------------- */

/* Companies: keep name (comercial), plus legal info */
INSERT INTO companies (id, name, razon_social, rut, giro, direccion)
VALUES
  (1, 'Empresa A', 'Empresa A', '', 'Servicios', ''),
  (2, 'Fipe - The Hybrid Mind Spa', 'Fipe - The Hybrid Mind Spa', '77.270.095-5', 'Diseño / Publicidad / Marketing', '')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  razon_social = VALUES(razon_social),
  rut = VALUES(rut),
  giro = VALUES(giro),
  direccion = VALUES(direccion);

/* Users (do not overwrite password_hash) */
INSERT IGNORE INTO users (id, email, name, password_hash, created_at)
VALUES
  (1, 'dimefipe@gmail.com', 'Fipe', '$2y$10$EoiN.wwVOj1APO0RHkKe2e.EmLYifywgewGYMQF7itksJCmTuIB4.', '2025-12-27 13:00:01');

/* User companies */
INSERT IGNORE INTO user_companies (user_id, company_id, role)
VALUES
  (1, 1, 'OWNER'),
  (1, 2, 'OWNER');

/* Default chart of accounts for company_id=1 */
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

COMMIT;
