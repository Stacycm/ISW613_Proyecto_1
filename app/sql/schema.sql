-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 08-11-2026 a las 01:41:04
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
-- Base de datos: `sigel`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `selector` varchar(65) NOT NULL,
  `validador_hash` varchar(255) NOT NULL,
  `expira_en` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `auth_tokens`
--

INSERT INTO `auth_tokens` (`id`, `usuario_id`, `selector`, `validador_hash`, `expira_en`) VALUES
(2, 1, '41d335068c4c7285059f0d17', '$2y$10$giVXt5pdk3UyxrjfjQhNkeiXs6D8nETQJ1T3tnxXEAAdDqyZFu.4e', '2026-12-03 20:48:00'),
(8, 1, '1302f79b653dea71fc70bdf9', '$2y$10$pYqi5LsDibQ.BYAan3SU9.p/Ggpyx2sIKzIN0hqS8e9a4JOG24LFS', '2026-12-08 01:29:39');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bitacora`
--

CREATE TABLE `bitacora` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(50) NOT NULL,
  `entidad` varchar(50) NOT NULL,
  `entidad_id` int(11) DEFAULT NULL,
  `valores_antes` text DEFAULT NULL,
  `valores_despues` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bitacora`
--

INSERT INTO `bitacora` (`id`, `usuario_id`, `accion`, `entidad`, `entidad_id`, `valores_antes`, `valores_despues`, `ip`, `creado_en`) VALUES
(1, 1, 'EDITAR', 'usuarios', 3, '{\"nombre\":\"Operador\",\"correo\":\"operador@gmail.com\",\"rol_id\":2}', '{\"nombre\":\"operador\",\"correo\":\"operador@gmail.com\",\"rol_id\":2}', '::1', '2026-10-31 16:59:21'),
(2, 1, 'EDITAR', 'usuarios', 3, '{\"nombre\":\"operador\",\"correo\":\"operador@gmail.com\",\"rol_id\":2}', '{\"nombre\":\"Operador\",\"correo\":\"operador@gmail.com\",\"rol_id\":2}', '::1', '2026-10-31 17:01:03'),
(3, 1, 'CREAR', 'prestamos', 3, NULL, '{\"usuario_id\":1,\"operador_id\":null,\"estado\":\"solicitado\",\"fecha_inicio\":\"2026-10-30\",\"fecha_fin\":\"2026-11-20\",\"equipos\":[1]}', '127.0.0.1', '2026-10-31 17:09:01'),
(4, 1, 'CREAR', 'equipos', 7, NULL, '{\"codigo\":\"comp-400\",\"nombre\":\"Dell\",\"categoria_id\":1,\"estado\":\"Disponible\",\"imagen_ruta\":\"uploads\\/equipos\\/b2fdea6b15303857fb1d8f849fc25e82.jpg\"}', '127.0.0.1', '2026-11-01 13:30:41'),
(5, 1, 'CREAR', 'equipos', 8, NULL, '{\"codigo\":\"comp-400\",\"nombre\":\"Dell\",\"categoria_id\":1,\"estado\":\"Disponible\",\"imagen_ruta\":\"uploads\\/equipos\\/3e4de5e22c956cbad1704b7836c9a4ea.jpg\"}', '127.0.0.1', '2026-11-01 13:36:51'),
(6, 1, 'CREAR', 'equipos', 9, NULL, '{\"codigo\":\"comp-400\",\"nombre\":\"Dell\",\"categoria_id\":1,\"estado\":\"Disponible\",\"imagen_ruta\":\"uploads\\/equipos\\/8302ae517f543d722c30c82f49fea092.jpg\"}', '127.0.0.1', '2026-11-01 13:41:58'),
(7, 1, 'CREAR', 'equipos', 11, NULL, '{\"codigo\":\"comp-400\",\"nombre\":\"Dell\",\"categoria_id\":1,\"estado\":\"Disponible\",\"imagen_ruta\":\"uploads\\/equipos\\/740e1e9c2ed0f2c00412d130d8565607.jpg\"}', '127.0.0.1', '2026-11-01 13:49:01'),
(8, 1, 'CREAR', 'equipos', 12, NULL, '{\"codigo\":\"comp-400\",\"nombre\":\"Dell\",\"categoria_id\":1,\"estado\":\"Disponible\",\"imagen_ruta\":\"uploads\\/equipos\\/8d869aadbb38f841e7bdeb73b386dbf5.jpg\"}', '127.0.0.1', '2026-11-01 14:02:49'),
(9, 1, 'CREAR', 'equipos', 13, NULL, '{\"codigo\":\"comp-400\",\"nombre\":\"Dell\",\"categoria_id\":1,\"estado\":\"Disponible\",\"imagen_ruta\":\"uploads\\/equipos\\/184affc3472e8afbe88d011fa7a58af0.jpg\"}', '127.0.0.1', '2026-11-01 14:05:00'),
(10, 1, 'CREAR', 'prestamos', 4, NULL, '{\"usuario_id\":1,\"operador_id\":null,\"estado\":\"solicitado\",\"fecha_inicio\":\"2026-11-01\",\"fecha_fin\":\"2026-11-11\",\"equipos\":[13]}', '127.0.0.1', '2026-11-01 14:09:30'),
(11, 1, 'APROBAR', 'prestamos', 4, '{\"estado\":\"solicitado\",\"operador_id\":null}', '{\"estado\":\"aprobado\",\"operador_id\":null}', '127.0.0.1', '2026-11-01 14:09:54'),
(12, 1, 'ENTREGAR', 'prestamos', 4, '{\"estado_prestamo\":\"aprobado\",\"equipos\":[{\"equipo_id\":13,\"codigo\":\"comp-400\",\"nombre\":\"Dell\",\"estado\":\"Disponible\"}]}', '{\"estado_prestamo\":\"entregado\",\"equipos\":[{\"equipo_id\":13,\"codigo\":\"comp-400\",\"nombre\":\"Dell\",\"estado\":\"Prestado\"}]}', '127.0.0.1', '2026-11-01 14:09:56'),
(13, 1, 'DEVOLVER', 'prestamos', 4, '{\"prestamo_id\":4,\"estado_prestamo\":\"entregado\",\"equipo_id\":13,\"codigo_equipo\":\"comp-400\",\"nombre_equipo\":\"Dell\",\"estado_equipo\":\"Prestado\",\"fecha_devolucion\":null}', '{\"prestamo_id\":4,\"estado_prestamo\":\"devuelto\",\"equipo_id\":13,\"codigo_equipo\":\"comp-400\",\"nombre_equipo\":\"Dell\",\"estado_equipo\":\"Disponible\",\"fecha_devolucion\":\"2026-11-01\",\"equipos_pendientes\":0}', '127.0.0.1', '2026-11-01 14:09:57'),
(14, 1, 'CANCELAR', 'prestamos', 3, '{\"estado\":\"solicitado\",\"operador_id\":null}', '{\"estado\":\"cancelado\",\"operador_id\":null}', '127.0.0.1', '2026-11-01 14:10:00'),
(15, 1, 'CREAR', 'prestamos', 5, NULL, '{\"usuario_id\":1,\"operador_id\":null,\"estado\":\"solicitado\",\"fecha_inicio\":\"2026-11-01\",\"fecha_fin\":\"2026-11-03\",\"equipos\":[1]}', '127.0.0.1', '2026-11-01 14:10:13'),
(16, 1, 'CANCELAR', 'prestamos', 5, '{\"estado\":\"solicitado\",\"operador_id\":null}', '{\"estado\":\"cancelado\",\"operador_id\":null}', '127.0.0.1', '2026-11-01 14:10:16'),
(17, 1, 'CREAR', 'prestamos', 6, NULL, '{\"usuario_id\":1,\"operador_id\":null,\"estado\":\"solicitado\",\"fecha_inicio\":\"2026-11-01\",\"fecha_fin\":\"2026-11-10\",\"equipos\":[13]}', '127.0.0.1', '2026-11-01 14:10:30'),
(18, 1, 'CANCELAR', 'prestamos', 6, '{\"estado\":\"solicitado\",\"operador_id\":null}', '{\"estado\":\"cancelado\",\"operador_id\":null}', '127.0.0.1', '2026-11-01 14:11:46'),
(19, 1, 'CREAR', 'prestamos', 7, NULL, '{\"usuario_id\":1,\"operador_id\":null,\"estado\":\"solicitado\",\"fecha_inicio\":\"2026-11-01\",\"fecha_fin\":\"2026-11-07\",\"equipos\":[13]}', '127.0.0.1', '2026-11-01 14:17:08'),
(20, 1, 'APROBAR', 'prestamos', 7, '{\"estado\":\"solicitado\",\"operador_id\":null}', '{\"estado\":\"aprobado\",\"operador_id\":null}', '127.0.0.1', '2026-11-01 14:17:25'),
(21, 1, 'ENTREGAR', 'prestamos', 7, '{\"estado_prestamo\":\"aprobado\",\"equipos\":[{\"equipo_id\":13,\"codigo\":\"comp-400\",\"nombre\":\"Dell\",\"estado\":\"Disponible\"}]}', '{\"estado_prestamo\":\"entregado\",\"equipos\":[{\"equipo_id\":13,\"codigo\":\"comp-400\",\"nombre\":\"Dell\",\"estado\":\"Prestado\"}]}', '127.0.0.1', '2026-11-01 14:17:27'),
(22, 1, 'DEVOLVER', 'prestamos', 7, '{\"prestamo_id\":7,\"estado_prestamo\":\"entregado\",\"equipo_id\":13,\"codigo_equipo\":\"comp-400\",\"nombre_equipo\":\"Dell\",\"estado_equipo\":\"Prestado\",\"fecha_devolucion\":null}', '{\"prestamo_id\":7,\"estado_prestamo\":\"devuelto\",\"equipo_id\":13,\"codigo_equipo\":\"comp-400\",\"nombre_equipo\":\"Dell\",\"estado_equipo\":\"Disponible\",\"fecha_devolucion\":\"2026-11-01\",\"equipos_pendientes\":0}', '127.0.0.1', '2026-11-01 14:17:28'),
(23, 1, 'CREAR', 'prestamos', 8, NULL, '{\"usuario_id\":1,\"operador_id\":null,\"estado\":\"solicitado\",\"fecha_inicio\":\"2026-11-01\",\"fecha_fin\":\"2026-11-07\",\"equipos\":[1]}', '127.0.0.1', '2026-11-01 14:18:45'),
(24, 3, 'CANCELAR', 'prestamos', 8, '{\"estado\":\"solicitado\",\"operador_id\":null}', '{\"estado\":\"cancelado\",\"operador_id\":null}', '127.0.0.1', '2026-11-01 14:46:07'),
(25, 1, 'CREAR', 'equipos', 14, NULL, '{\"codigo\":\"comp-500\",\"nombre\":\"Dell\",\"categoria_id\":1,\"estado\":\"Disponible\",\"imagen_ruta\":\"uploads\\/equipos\\/90fc053c53ad6273b310af260ff3f166.jpg\"}', '127.0.0.1', '2026-11-03 13:46:04'),
(26, 1, 'ELIMINAR', 'equipos', 14, '{\"codigo\":\"comp-500\",\"nombre\":\"Dell\",\"categoria_id\":1,\"estado\":\"Disponible\",\"eliminado_en\":null}', '{\"codigo\":\"comp-500\",\"nombre\":\"Dell\",\"categoria_id\":1,\"estado\":\"Disponible\",\"eliminado_en\":\"2026-11-03 13:49:47\"}', '127.0.0.1', '2026-11-03 13:49:47'),
(27, 3, 'CREAR', 'prestamos', 9, NULL, '{\"usuario_id\":3,\"operador_id\":3,\"estado\":\"solicitado\",\"fecha_inicio\":\"2026-11-04\",\"fecha_fin\":\"2026-11-05\",\"dias_solicitados\":1,\"equipos\":[13]}', '127.0.0.1', '2026-11-03 14:19:52'),
(28, 3, 'APROBAR', 'prestamos', 9, '{\"estado\":\"solicitado\",\"operador_id\":3}', '{\"estado\":\"aprobado\",\"operador_id\":3}', '127.0.0.1', '2026-11-03 14:19:56'),
(29, 3, 'ENTREGAR', 'prestamos', 9, '{\"estado_prestamo\":\"aprobado\",\"equipos\":[{\"equipo_id\":13,\"codigo\":\"comp-400\",\"nombre\":\"Dell\",\"estado\":\"Disponible\"}]}', '{\"estado_prestamo\":\"entregado\",\"equipos\":[{\"equipo_id\":13,\"codigo\":\"comp-400\",\"nombre\":\"Dell\",\"estado\":\"Prestado\"}]}', '127.0.0.1', '2026-11-03 14:19:57'),
(30, 3, 'DEVOLVER', 'prestamos', 9, '{\"prestamo_id\":9,\"estado_prestamo\":\"entregado\",\"equipo_id\":13,\"codigo_equipo\":\"comp-400\",\"nombre_equipo\":\"Dell\",\"estado_equipo\":\"Prestado\",\"fecha_devolucion\":null}', '{\"prestamo_id\":9,\"estado_prestamo\":\"devuelto\",\"equipo_id\":13,\"codigo_equipo\":\"comp-400\",\"nombre_equipo\":\"Dell\",\"estado_equipo\":\"Disponible\",\"fecha_devolucion\":\"2026-11-03\",\"equipos_pendientes\":0}', '127.0.0.1', '2026-11-03 14:20:01'),
(31, 1, 'CREAR', 'prestamos', 10, NULL, '{\"usuario_id\":3,\"operador_id\":null,\"estado\":\"solicitado\",\"fecha_inicio\":\"2026-11-08\",\"fecha_fin\":\"2026-11-09\",\"dias_solicitados\":1,\"equipos\":[1,13]}', '127.0.0.1', '2026-11-07 18:30:16'),
(32, 1, 'CANCELAR', 'prestamos', 10, '{\"estado\":\"solicitado\",\"operador_id\":null}', '{\"estado\":\"cancelado\",\"operador_id\":null}', '127.0.0.1', '2026-11-07 18:30:22'),
(33, 1, 'EDITAR', 'equipos', 5, '{\"codigo\":\"pro-200\",\"nombre\":\"Edson\",\"categoria_id\":2,\"estado\":\"Mantenimiento\",\"imagen_ruta\":null}', '{\"codigo\":\"pro-200\",\"nombre\":\"Edson\",\"categoria_id\":2,\"estado\":\"Disponible\",\"imagen_ruta\":null}', '127.0.0.1', '2026-11-07 18:30:44'),
(34, 1, 'EDITAR', 'equipos', 2, '{\"codigo\":\"lab-001\",\"nombre\":\"Microscopio\",\"categoria_id\":3,\"estado\":\"Prestado\",\"imagen_ruta\":null}', '{\"codigo\":\"lab-001\",\"nombre\":\"Microscopio\",\"categoria_id\":3,\"estado\":\"Disponible\",\"imagen_ruta\":null}', '127.0.0.1', '2026-11-07 18:30:50'),
(35, 1, 'CREAR', 'equipos', 19, NULL, '{\"codigo\":\"lab-002\",\"nombre\":\"Tubo\",\"categoria_id\":3,\"estado\":\"Disponible\",\"imagen_ruta\":null}', '127.0.0.1', '2026-11-07 18:31:29'),
(36, 1, 'CREAR', 'equipos', 20, NULL, '{\"codigo\":\"pro-100\",\"nombre\":\"Edson\",\"categoria_id\":2,\"estado\":\"Disponible\",\"imagen_ruta\":null}', '127.0.0.1', '2026-11-07 18:32:04'),
(37, 1, 'CREAR', 'prestamos', 11, NULL, '{\"usuario_id\":3,\"operador_id\":null,\"estado\":\"solicitado\",\"fecha_inicio\":\"2026-11-08\",\"fecha_fin\":\"2026-11-09\",\"dias_solicitados\":1,\"equipos\":[1,2,5,13,20]}', '127.0.0.1', '2026-11-07 18:40:38'),
(38, 1, 'CANCELAR', 'prestamos', 11, '{\"estado\":\"solicitado\",\"operador_id\":null}', '{\"estado\":\"cancelado\",\"operador_id\":null}', '127.0.0.1', '2026-11-07 18:40:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria`
--

CREATE TABLE `categoria` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `dias_max_prestamo` int(11) NOT NULL,
  `eliminado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categoria`
--

INSERT INTO `categoria` (`id`, `nombre`, `dias_max_prestamo`, `eliminado_en`) VALUES
(1, 'Computadoras', 1, NULL),
(2, 'Proyectores', 6, NULL),
(3, 'Equipo de laboratorio', 6, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `clave` varchar(100) NOT NULL,
  `valor` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`clave`, `valor`) VALUES
('max_equipos_prestamo', '5');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipos`
--

CREATE TABLE `equipos` (
  `id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `estado` varchar(30) NOT NULL,
  `imagen_ruta` varchar(255) DEFAULT NULL,
  `version` int(11) DEFAULT 1,
  `eliminado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `equipos`
--

INSERT INTO `equipos` (`id`, `categoria_id`, `codigo`, `nombre`, `estado`, `imagen_ruta`, `version`, `eliminado_en`) VALUES
(1, 1, 'comp-300', 'Dell', 'Disponible', '', 4, NULL),
(2, 3, 'lab-001', 'Microscopio', 'Disponible', NULL, 2, NULL),
(5, 2, 'pro-200', 'Edson', 'Disponible', NULL, 2, NULL),
(13, 1, 'comp-400', 'Dell', 'Disponible', 'uploads/equipos/184affc3472e8afbe88d011fa7a58af0.jpg', 7, NULL),
(14, 1, 'comp-500', 'Dell', 'Disponible', 'uploads/equipos/90fc053c53ad6273b310af260ff3f166.jpg', 2, '2026-11-03 13:49:47'),
(19, 3, 'lab-002', 'Tubo', 'Disponible', NULL, 1, NULL),
(20, 2, 'pro-100', 'Edson', 'Disponible', NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id` int(11) NOT NULL,
  `clave` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id`, `clave`) VALUES
(18, 'bitacora_consultar'),
(5, 'categoria_consultar'),
(6, 'categoria_crear'),
(7, 'categoria_editar'),
(8, 'categoria_eliminar'),
(9, 'equipo_consultar'),
(10, 'equipo_crear'),
(11, 'equipo_editar'),
(12, 'equipo_eliminar'),
(13, 'prestamo_consultar'),
(15, 'prestamo_devoluciones'),
(14, 'prestamo_gestionar_solicitudes'),
(16, 'reporte_consultar'),
(17, 'reporte_exportar'),
(1, 'usuario_consultar'),
(2, 'usuario_crear'),
(4, 'usuario_desactivar'),
(3, 'usuario_editar');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamos`
--

CREATE TABLE `prestamos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `operador_id` int(11) DEFAULT NULL,
  `estado` varchar(30) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `prestamos`
--

INSERT INTO `prestamos` (`id`, `usuario_id`, `operador_id`, `estado`, `fecha_inicio`, `fecha_fin`) VALUES
(1, 3, NULL, 'devuelto', '2026-10-31', '2026-11-15'),
(2, 1, NULL, 'cancelado', '2026-10-31', '2026-11-01'),
(3, 1, NULL, 'cancelado', '2026-10-30', '2026-11-20'),
(4, 1, NULL, 'devuelto', '2026-11-01', '2026-11-11'),
(5, 1, NULL, 'cancelado', '2026-11-01', '2026-11-03'),
(6, 1, NULL, 'cancelado', '2026-11-01', '2026-11-10'),
(7, 1, NULL, 'devuelto', '2026-11-01', '2026-11-07'),
(8, 1, NULL, 'cancelado', '2026-11-01', '2026-11-07'),
(9, 3, 3, 'devuelto', '2026-11-04', '2026-11-05'),
(10, 3, NULL, 'cancelado', '2026-11-08', '2026-11-09'),
(11, 3, NULL, 'cancelado', '2026-11-08', '2026-11-09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamo_detalle`
--

CREATE TABLE `prestamo_detalle` (
  `prestamo_id` int(11) NOT NULL,
  `equipo_id` int(11) NOT NULL,
  `fecha_devolucion` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `prestamo_detalle`
--

INSERT INTO `prestamo_detalle` (`prestamo_id`, `equipo_id`, `fecha_devolucion`) VALUES
(1, 1, '2026-10-31'),
(2, 1, NULL),
(3, 1, NULL),
(4, 13, '2026-11-01'),
(5, 1, NULL),
(6, 13, NULL),
(7, 13, '2026-11-01'),
(8, 1, NULL),
(9, 13, '2026-11-03'),
(10, 1, NULL),
(10, 13, NULL),
(11, 1, NULL),
(11, 2, NULL),
(11, 5, NULL),
(11, 13, NULL),
(11, 20, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`) VALUES
(1, 'Administrador'),
(2, 'Operador');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permiso`
--

CREATE TABLE `rol_permiso` (
  `rol_id` int(11) NOT NULL,
  `permiso_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol_permiso`
--

INSERT INTO `rol_permiso` (`rol_id`, `permiso_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(2, 9),
(2, 13),
(2, 14),
(2, 15);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `intentos_fallidos` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `rol_id`, `nombre`, `correo`, `password_hash`, `activo`, `intentos_fallidos`) VALUES
(1, 1, 'Admin', 'admin@gmail.com', '$2y$10$qn8T3EMH8UNZAGJShrI/9ujf3aQdaxWhlw.Jvs7s90YDIgL/JlGpC', 1, 0),
(3, 2, 'Operador', 'operador@gmail.com', '$2y$10$ZiMttNVlCAQX8bg4DXbNceGr9Wo/xZBsOtK0Ksjog5LnkoxZUBvQC', 1, 0);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `selector` (`selector`),
  ADD KEY `usuario_auth_fk` (`usuario_id`);

--
-- Indices de la tabla `bitacora`
--
ALTER TABLE `bitacora`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_bitacora_fk` (`usuario_id`);

--
-- Indices de la tabla `categoria`
--
ALTER TABLE `categoria`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_uq` (`nombre`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`clave`);

--
-- Indices de la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_uq` (`codigo`),
  ADD KEY `categoria_equipo_fk` (`categoria_id`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clave_uq` (`clave`);

--
-- Indices de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prestamos_usuario_fk` (`usuario_id`),
  ADD KEY `prestamos_operador_fk` (`operador_id`);

--
-- Indices de la tabla `prestamo_detalle`
--
ALTER TABLE `prestamo_detalle`
  ADD KEY `prestamoD_prestamo_fk` (`prestamo_id`),
  ADD KEY `prestamoD_equipo_fk` (`equipo_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_uq` (`nombre`);

--
-- Indices de la tabla `rol_permiso`
--
ALTER TABLE `rol_permiso`
  ADD PRIMARY KEY (`rol_id`,`permiso_id`),
  ADD KEY `permisos_fk` (`permiso_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo_uq` (`correo`),
  ADD KEY `roles_usuario_fk` (`rol_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `bitacora`
--
ALTER TABLE `bitacora`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT de la tabla `categoria`
--
ALTER TABLE `categoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `equipos`
--
ALTER TABLE `equipos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `usuario_auth_fk` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `bitacora`
--
ALTER TABLE `bitacora`
  ADD CONSTRAINT `usuario_bitacora_fk` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD CONSTRAINT `categoria_equipo_fk` FOREIGN KEY (`categoria_id`) REFERENCES `categoria` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD CONSTRAINT `prestamos_operador_fk` FOREIGN KEY (`operador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `prestamos_usuario_fk` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `prestamo_detalle`
--
ALTER TABLE `prestamo_detalle`
  ADD CONSTRAINT `prestamoD_equipo_fk` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `prestamoD_prestamo_fk` FOREIGN KEY (`prestamo_id`) REFERENCES `prestamos` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `rol_permiso`
--
ALTER TABLE `rol_permiso`
  ADD CONSTRAINT `permisos_fk` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `permisos_rol_fk` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `roles_usuario_fk` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
