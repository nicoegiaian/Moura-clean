-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 27-10-2025 a las 21:07:01
-- Versión del servidor: 11.8.3-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u156146850_moura_test`
--

--
-- Volcado de datos para la tabla `liquidacionesdetalle`
--

INSERT INTO `liquidacionesdetalle` (`nrotransaccion`, `comisionpd`, `ivacomisionpd`, `subsidiomoura`, `ivasubsidiomoura`, `comisionprontopago`, `ivacomisionprontopago`, `descuentocuotas`, `ivadescuentocuotas`, `costoacreditacion`, `ivacostoacreditacion`, `aranceltarjeta`, `ivaaranceltarjeta`, `credmoura`, `sirtac`, `otrosimpuestos`, `beneficiocredmoura`) VALUES
(63582208, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2321.82, 487.58, 0.00, 0.00, 0.00, 644.95),
(271084842, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2089.64, 438.82, 0.00, 0.00, 0.00, 580.46),
(365129535, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2969.82, 623.66, 0.00, 0.00, 0.00, 824.95),
(504974250, 0.00, 2.63, 3.48, 0.00, 0.00, 0.00, 24.73, 0.00, 0.00, 0.00, 1.80, 0.00, 2.62, 0.00, 0.00, 9.51),
(517999539, 0.00, 1.54, 3.27, 0.00, 0.00, 0.00, 15.12, 0.00, 0.00, 0.00, 1.80, 0.00, 2.46, 0.00, 0.00, 8.04),
(580644304, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 24991.55, 0.00, 0.00, 0.00, 2975.18, 2538.82, 0.00, 0.00, 0.00, 13289.16),
(647552387, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4661.82, 978.98, 0.00, 0.00, 0.00, 1294.95),
(690758213, 0.00, 0.38, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1.80, 0.00, 2.26, 0.00, 0.00, 0.05),
(779417958, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2755.62, 578.68, 0.00, 0.00, 0.00, 765.45),
(852350050, 0.00, 2.63, 0.00, 0.00, 0.00, 0.00, 24.73, 0.00, 0.00, 0.00, 1.80, 0.00, 0.00, 0.00, 0.00, 9.51),
(948403273, 0.00, 0.38, 3.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1.80, 0.00, 0.05, 0.00, 0.00, 0.05);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
