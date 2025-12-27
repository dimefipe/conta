DROP DATABASE IF EXISTS conta_mvp;
CREATE DATABASE conta_mvp CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE conta_mvp;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- =========================
-- USERS
-- =========================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(120) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- COMPANIES
-- =========================
CREATE TABLE companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  rut  VARCHAR(20) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- USER <-> COMPANIES (accesos)
-- Un usuario puede ver varias empresas
-- =========================
CREATE TABLE user_companies (
  user_id INT NOT NULL,
  company_id INT NOT NULL,
  role ENUM('OWNER','ADMIN','VIEWER') NOT NULL DEFAULT 'OWNER',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, company_id),
  CONSTRAINT fk_uc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_uc_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- ACCOUNTS (Plan de cuentas)
-- OJO: el código debe ser único POR empresa (company_id, code)
-- =========================
CREATE TABLE accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(120) NOT NULL,
  type ENUM('ASSET','LIABILITY','EQUITY','INCOME','COST','EXPENSE') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_company_code (company_id, code),
  KEY idx_accounts_company (company_id),
  CONSTRAINT fk_accounts_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- JOURNAL ENTRIES (Libro diario)
-- company_id separa empresas
-- =========================
CREATE TABLE journal_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  entry_date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  status ENUM('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  voided_at DATETIME DEFAULT NULL,
  KEY idx_entries_company_date (company_id, entry_date),
  CONSTRAINT fk_entries_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- JOURNAL LINES
-- =========================
CREATE TABLE journal_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_id INT NOT NULL,
  line_no INT NOT NULL,
  account_id INT NOT NULL,
  memo VARCHAR(255) DEFAULT NULL,
  debit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  credit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  UNIQUE KEY uq_entry_line (entry_id, line_no),
  KEY idx_lines_entry (entry_id),
  KEY idx_lines_account (account_id),
  CONSTRAINT fk_lines_entry FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_lines_account FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TRIGGERS: consistencia empresa
-- (Evita mezclar cuentas de empresa A en asiento de empresa B)
-- =========================
DELIMITER //

CREATE TRIGGER trg_lines_company_check_ins
BEFORE INSERT ON journal_lines
FOR EACH ROW
BEGIN
  DECLARE acc_company INT;
  DECLARE ent_company INT;

  SELECT company_id INTO acc_company FROM accounts WHERE id = NEW.account_id;
  SELECT company_id INTO ent_company FROM journal_entries WHERE id = NEW.entry_id;

  IF acc_company IS NULL OR ent_company IS NULL OR acc_company <> ent_company THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Cuenta y asiento deben pertenecer a la misma empresa';
  END IF;
END//

CREATE TRIGGER trg_lines_company_check_upd
BEFORE UPDATE ON journal_lines
FOR EACH ROW
BEGIN
  DECLARE acc_company INT;
  DECLARE ent_company INT;

  SELECT company_id INTO acc_company FROM accounts WHERE id = NEW.account_id;
  SELECT company_id INTO ent_company FROM journal_entries WHERE id = NEW.entry_id;

  IF acc_company IS NULL OR ent_company IS NULL OR acc_company <> ent_company THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Cuenta y asiento deben pertenecer a la misma empresa';
  END IF;
END//

DELIMITER ;

-- =========================
-- SEED: usuario + empresa + acceso + plan cuentas ejemplo
-- =========================

INSERT INTO users (email, name, password_hash)
VALUES
('dimefipe@gmail.com', 'Fipe', '$2y$10$EoiN.wwVOj1APO0RHkKe2e.EmLYifywgewGYMQF7itksJCmTuIB4.');

INSERT INTO companies (name, rut) VALUES ('Empresa A','');

-- user 1 es dueño de Empresa 1
INSERT INTO user_companies (user_id, company_id, role) VALUES (1, 1, 'OWNER');

-- plan de cuentas base para Empresa A (company_id=1)
INSERT INTO accounts (company_id, code, name, type, is_active) VALUES
(1,'1101','Caja','ASSET',1),
(1,'1102','Banco','ASSET',1),
(1,'1103','Clientes por cobrar (CxC)','ASSET',1),
(1,'1104','IVA crédito fiscal','ASSET',1),
(1,'2101','Proveedores por pagar (CxP)','LIABILITY',1),
(1,'2102','IVA débito fiscal','LIABILITY',1),
(1,'2103','Impuestos por pagar','LIABILITY',1),
(1,'3101','Capital','EQUITY',1),
(1,'3102','Resultado acumulado','EQUITY',1),
(1,'4101','Ingresos - Marketing / RRSS','INCOME',1),
(1,'4102','Ingresos - Branding','INCOME',1),
(1,'4103','Ingresos - Desarrollo Web','INCOME',1),
(1,'4104','Ingresos - Producción Audiovisual','INCOME',1),
(1,'4105','Ingresos - Consultorías','INCOME',1),
(1,'5101','Costos - Freelancers / Producción','COST',1),
(1,'5102','Costos - Subcontratos','COST',1),
(1,'6101','Gastos - Software / Suscripciones','EXPENSE',1),
(1,'6102','Gastos - Publicidad (propia)','EXPENSE',1),
(1,'6103','Gastos - Arriendo / Cowork','EXPENSE',1),
(1,'6104','Gastos - Internet / Telefonía','EXPENSE',1),
(1,'6105','Gastos - Transporte','EXPENSE',1),
(1,'6106','Gastos - Contabilidad / Legal','EXPENSE',1),
(1,'6107','Gastos - Bancarios','EXPENSE',1);
