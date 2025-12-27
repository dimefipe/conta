CREATE DATABASE IF NOT EXISTS conta_mvp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE conta_mvp;

DROP TABLE IF EXISTS journal_lines;
DROP TABLE IF EXISTS journal_entries;
DROP TABLE IF EXISTS accounts;

CREATE TABLE accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  type ENUM('ASSET','LIABILITY','EQUITY','INCOME','COST','EXPENSE') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE journal_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_date DATE NOT NULL,
  description VARCHAR(255) NOT NULL,
  status ENUM('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  voided_at DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE journal_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_id INT NOT NULL,
  line_no INT NOT NULL,
  account_id INT NOT NULL,
  memo VARCHAR(255) NULL,
  debit DECIMAL(14,2) NOT NULL DEFAULT 0,
  credit DECIMAL(14,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_lines_entry FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_lines_account FOREIGN KEY (account_id) REFERENCES accounts(id),
  INDEX idx_lines_entry (entry_id),
  INDEX idx_lines_account (account_id),
  UNIQUE KEY uq_entry_line (entry_id, line_no)
) ENGINE=InnoDB;

-- ====== Seed: Plan de cuentas (agencia) ======
INSERT INTO accounts(code,name,type) VALUES
('1101','Caja','ASSET'),
('1102','Banco','ASSET'),
('1103','Clientes por cobrar (CxC)','ASSET'),
('1104','IVA crédito fiscal','ASSET'),

('2101','Proveedores por pagar (CxP)','LIABILITY'),
('2102','IVA débito fiscal','LIABILITY'),
('2103','Impuestos por pagar','LIABILITY'),

('3101','Capital','EQUITY'),
('3102','Resultado acumulado','EQUITY'),

('4101','Ingresos - Marketing / RRSS','INCOME'),
('4102','Ingresos - Branding','INCOME'),
('4103','Ingresos - Desarrollo Web','INCOME'),
('4104','Ingresos - Producción Audiovisual','INCOME'),
('4105','Ingresos - Consultorías','INCOME'),

('5101','Costos - Freelancers / Producción','COST'),
('5102','Costos - Subcontratos','COST'),

('6101','Gastos - Software / Suscripciones','EXPENSE'),
('6102','Gastos - Publicidad (propia)','EXPENSE'),
('6103','Gastos - Arriendo / Cowork','EXPENSE'),
('6104','Gastos - Internet / Telefonía','EXPENSE'),
('6105','Gastos - Transporte','EXPENSE'),
('6106','Gastos - Contabilidad / Legal','EXPENSE'),
('6107','Gastos - Bancarios','EXPENSE');


CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(120) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;