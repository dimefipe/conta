-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 27-12-2025 a las 21:32:11
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `conta_mvp`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(120) NOT NULL,
  `type` enum('ASSET','LIABILITY','EQUITY','INCOME','COST','EXPENSE') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `accounts`
--

INSERT INTO `accounts` (`id`, `company_id`, `code`, `name`, `type`, `is_active`, `created_at`) VALUES
(1, 1, '1101', 'Caja', 'ASSET', 1, '2025-12-27 13:00:01'),
(2, 1, '1102', 'Banco', 'ASSET', 1, '2025-12-27 13:00:01'),
(3, 1, '1103', 'Clientes por cobrar (CxC)', 'ASSET', 1, '2025-12-27 13:00:01'),
(4, 1, '1104', 'IVA crédito fiscal', 'ASSET', 1, '2025-12-27 13:00:01'),
(5, 1, '2101', 'Proveedores por pagar (CxP)', 'LIABILITY', 1, '2025-12-27 13:00:01'),
(6, 1, '2102', 'IVA débito fiscal', 'LIABILITY', 1, '2025-12-27 13:00:01'),
(7, 1, '2103', 'Impuestos por pagar', 'LIABILITY', 1, '2025-12-27 13:00:01'),
(8, 1, '3101', 'Capital', 'EQUITY', 1, '2025-12-27 13:00:01'),
(9, 1, '3102', 'Resultado acumulado', 'EQUITY', 1, '2025-12-27 13:00:01'),
(10, 1, '4101', 'Ingresos - Marketing / RRSS', 'INCOME', 1, '2025-12-27 13:00:01'),
(11, 1, '4102', 'Ingresos - Branding', 'INCOME', 1, '2025-12-27 13:00:01'),
(12, 1, '4103', 'Ingresos - Desarrollo Web', 'INCOME', 1, '2025-12-27 13:00:01'),
(13, 1, '4104', 'Ingresos - Producción Audiovisual', 'INCOME', 1, '2025-12-27 13:00:01'),
(14, 1, '4105', 'Ingresos - Consultorías', 'INCOME', 1, '2025-12-27 13:00:01'),
(15, 1, '5101', 'Costos - Freelancers / Producción', 'COST', 1, '2025-12-27 13:00:01'),
(16, 1, '5102', 'Costos - Subcontratos', 'COST', 1, '2025-12-27 13:00:01'),
(17, 1, '6101', 'Gastos - Software / Suscripciones', 'EXPENSE', 1, '2025-12-27 13:00:01'),
(18, 1, '6102', 'Gastos - Publicidad (propia)', 'EXPENSE', 1, '2025-12-27 13:00:01'),
(19, 1, '6103', 'Gastos - Arriendo / Cowork', 'EXPENSE', 1, '2025-12-27 13:00:01'),
(20, 1, '6104', 'Gastos - Internet / Telefonía', 'EXPENSE', 1, '2025-12-27 13:00:01'),
(21, 1, '6105', 'Gastos - Transporte', 'EXPENSE', 1, '2025-12-27 13:00:01'),
(22, 1, '6106', 'Gastos - Contabilidad / Legal', 'EXPENSE', 1, '2025-12-27 13:00:01'),
(23, 1, '6107', 'Gastos - Bancarios', 'EXPENSE', 1, '2025-12-27 13:00:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `rut` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `companies`
--

INSERT INTO `companies` (`id`, `name`, `rut`, `created_at`) VALUES
(1, 'Empresa A', '', '2025-12-27 13:00:01'),
(2, 'Fipe - The Hybrid Mind Spa', '77.270.095-5', '2025-12-27 13:44:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `journal_entries`
--

CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `status` enum('POSTED','VOID') NOT NULL DEFAULT 'POSTED',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `voided_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `journal_lines`
--

CREATE TABLE `journal_lines` (
  `id` int(11) NOT NULL,
  `entry_id` int(11) NOT NULL,
  `line_no` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `memo` varchar(255) DEFAULT NULL,
  `debit` decimal(14,0) NOT NULL DEFAULT 0,
  `credit` decimal(14,0) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Disparadores `journal_lines`
--
DELIMITER $$
CREATE TRIGGER `trg_entry_balance_check_ai` AFTER INSERT ON `journal_lines` FOR EACH ROW BEGIN
  DECLARE d DECIMAL(14,2);
  DECLARE c DECIMAL(14,2);

  SELECT COALESCE(SUM(debit),0), COALESCE(SUM(credit),0)
    INTO d, c
  FROM journal_lines
  WHERE entry_id = NEW.entry_id;

  IF d <> c THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Asiento descuadrado: Debe y Haber deben ser iguales';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_lines_company_check_ins` BEFORE INSERT ON `journal_lines` FOR EACH ROW BEGIN
  DECLARE acc_company INT;
  DECLARE ent_company INT;

  SELECT company_id INTO acc_company FROM accounts WHERE id = NEW.account_id;
  SELECT company_id INTO ent_company FROM journal_entries WHERE id = NEW.entry_id;

  IF acc_company IS NULL OR ent_company IS NULL OR acc_company <> ent_company THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Cuenta y asiento deben pertenecer a la misma empresa';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_lines_company_check_upd` BEFORE UPDATE ON `journal_lines` FOR EACH ROW BEGIN
  DECLARE acc_company INT;
  DECLARE ent_company INT;

  SELECT company_id INTO acc_company FROM accounts WHERE id = NEW.account_id;
  SELECT company_id INTO ent_company FROM journal_entries WHERE id = NEW.entry_id;

  IF acc_company IS NULL OR ent_company IS NULL OR acc_company <> ent_company THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Cuenta y asiento deben pertenecer a la misma empresa';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(120) NOT NULL,
  `name` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `email`, `name`, `password_hash`, `created_at`) VALUES
(1, 'dimefipe@gmail.com', 'Fipe', '$2y$10$EoiN.wwVOj1APO0RHkKe2e.EmLYifywgewGYMQF7itksJCmTuIB4.', '2025-12-27 13:00:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `user_companies`
--

CREATE TABLE `user_companies` (
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `role` enum('OWNER','ADMIN','VIEWER') NOT NULL DEFAULT 'OWNER',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `user_companies`
--

INSERT INTO `user_companies` (`user_id`, `company_id`, `role`, `created_at`) VALUES
(1, 1, 'OWNER', '2025-12-27 13:00:01'),
(1, 2, 'OWNER', '2025-12-27 13:44:02');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_company_code` (`company_id`,`code`),
  ADD KEY `idx_accounts_company` (`company_id`);

--
-- Indices de la tabla `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company_name` (`name`);

--
-- Indices de la tabla `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entries_company_date` (`company_id`,`entry_date`);

--
-- Indices de la tabla `journal_lines`
--
ALTER TABLE `journal_lines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_entry_line` (`entry_id`,`line_no`),
  ADD KEY `idx_lines_entry` (`entry_id`),
  ADD KEY `idx_lines_account` (`account_id`);

--
-- Indices de la tabla `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `user_companies`
--
ALTER TABLE `user_companies`
  ADD PRIMARY KEY (`user_id`,`company_id`),
  ADD KEY `fk_uc_company` (`company_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `journal_entries`
--
ALTER TABLE `journal_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `journal_lines`
--
ALTER TABLE `journal_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_accounts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD CONSTRAINT `fk_entries_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `journal_lines`
--
ALTER TABLE `journal_lines`
  ADD CONSTRAINT `fk_lines_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_lines_entry` FOREIGN KEY (`entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `user_companies`
--
ALTER TABLE `user_companies`
  ADD CONSTRAINT `fk_uc_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_uc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
