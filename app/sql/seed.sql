-- Datos iniciales para la base de datos

-- 1. Roles del sistema 
INSERT INTO roles (id, nombre) VALUES 
(1, 'Administrador'),
(2, 'Operador');

-- 2. Permisos del sistema
INSERT INTO permisos (id, clave) VALUES 
(1, 'usuario_consular'),
(2, 'usuario_crear'),
(3, 'usuario_editar'),
(4, 'usuario_desactivar'),
(5, 'categoria_consultar'),
(6, 'categoria_crear'),
(7, 'categoria_editar'),
(8, 'categoria_eliminar'),
(9, 'equipo_consultar'),
(10, 'equipo_crear'),
(11, 'equipo_editar'),
(12, 'equipo_eliminar'),
(13, 'prestamo_consultar'),
(14, 'prestamo_gestionar_solicitudes'),
(15, 'prestamo_devoluciones'),
(16, 'reporte_consultar'),
(17, 'reporte_exportar');

-- 3. Asignación de permisos a roles

-- Permisos para el rol Administrador
INSERT INTO roles_permisos (rol_id, permiso_id) VALUES 
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
(1, 17);

-- Permisos para el rol Operador
INSERT INTO roles_permisos (rol_id, permiso_id) VALUES 
(2, 9),
(2, 13),
(2, 14),
(2, 15);

-- 4. Categorías iniciales
INSERT INTO categorias (id, nombre, dias_max_prestamo) VALUES 
(1, 'Computadoras', 3),
(2, 'Proyectores', 5),
(3, 'Equipo de laboratorio', 7);

-- 5. Equipos iniciales
INSERT INTO equipos (id, categoria_id, codigo, nombre, estado, imagen_ruta, version, eliminado_en) VALUES
(1, 1, 'comp-300', 'Laptop', 'Disponible', NULL, 1, NULL),
(5, 3, 'lab-001', 'Microscopio', 'Prestado', NULL, 1, NULL),
(2, 2, 'pro-200', 'Edson', 'Mantenimiento', NULL, 1, NULL);

