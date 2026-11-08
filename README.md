# SIGEL – Sistema de Gestión de Equipos y Préstamos

## 1. Descripción del proyecto

SIGEL es un sistema web desarrollado para gestionar equipos, categorías, usuarios y préstamos. Permite administrar las solicitudes de préstamo, controlar las entregas y devoluciones de equipos y consultar los registros de actividad del sistema.

El sistema cuenta con diferentes roles de usuario y permisos para controlar el acceso a sus funcionalidades, facilitando la administración y el seguimiento de los equipos.

## 2. Tecnologías utilizadas

- **PHP 8.2:** desarrollo del sistema.
- **MySQL / MariaDB:** gestión de la base de datos.
- **PDO:** conexión y consultas a la base de datos.
- **HTML5:** estructura de las páginas.
- **CSS3:** diseño y estilos visuales.
- **Apache:** servidor web.
- **XAMPP:** entorno de desarrollo local.

## 3. Roles del sistema

### Administrador

Tiene acceso a las funciones administrativas del sistema:

- Consultar, crear, editar y desactivar usuarios.
- Gestionar categorías.
- Gestionar equipos.
- Consultar y gestionar préstamos.
- Aprobar solicitudes.
- Registrar entregas y devoluciones.
- Consultar y exportar reportes.
- Consultar la bitácora del sistema.

### Operador

Tiene permisos relacionados con la gestión de equipos y préstamos:

- Consultar equipos.
- Consultar préstamos.
- Gestionar solicitudes de préstamo.
- Registrar entregas y devoluciones.

El operador no tiene acceso a la administración de usuarios, reportes ni bitácora.

## 4. Módulos principales

| Módulo | Descripción |
|---|---|
| Inicio de sesión | Autenticación de usuarios y control de acceso. |
| Dashboard | Panel principal según el rol del usuario. |
| Usuarios | Administración de usuarios del sistema. |
| Categorías | Gestión de categorías de equipos. |
| Equipos | Registro y administración de equipos. |
| Préstamos | Gestión de solicitudes, aprobaciones, entregas y devoluciones. |
| Reportes | Consulta y exportación de información. |
| Bitácora | Registro de las actividades realizadas en el sistema. |

## 5. Reglas de negocio principales

El sistema incorpora validaciones para garantizar el correcto funcionamiento de los préstamos y la administración de los equipos.

Entre ellas:

- Solo se pueden solicitar equipos disponibles.
- Se verifica que el usuario esté activo.
- Se valida la duración del préstamo según los días máximos permitidos por categoría.
- Se comprueba que las fechas de los préstamos no se superpongan con otras solicitudes o préstamos que ocupen los mismos equipos.
- Se evita registrar equipos duplicados dentro de una misma solicitud.
- Se establece un máximo de **5 equipos por préstamo**, configurable mediante la tabla `configuracion` de la base de datos.

## 6. Requisitos para ejecutar el proyecto

Antes de ejecutar SIGEL, es necesario contar con:

- XAMPP instalado.
- Apache habilitado.
- MySQL o MariaDB habilitado.
- PHP 8.2 o compatible.
- Un navegador web.
- phpMyAdmin para administrar la base de datos.
- Git para clonar el repositorio.

## 7. Instalación y configuración

### Paso 1. Clonar el repositorio

Clonar el repositorio de Git dentro de la carpeta `htdocs` de XAMPP.

```bash
git clone https://github.com/Stacycm/ISW613_Proyecto_1.git
```

### Paso 2. Configurar la base de datos

1. Iniciar Apache y MySQL desde XAMPP.
2. Abrir phpMyAdmin.
3. Crear una base de datos llamada `sigel`.
4. Importar primero el archivo correspondiente al esquema de la base de datos.
5. Importar el archivo `seed.sql` para cargar los datos iniciales.

Si la tabla `configuracion` no está incluida en los archivos SQL, ejecutar también:

```sql
CREATE TABLE IF NOT EXISTS configuracion (
    clave VARCHAR(100) PRIMARY KEY,
    valor VARCHAR(255) NOT NULL
);

INSERT IGNORE INTO configuracion (clave, valor)
VALUES ('max_equipos_prestamo', '5');
```

Esta configuración establece inicialmente un máximo de 5 equipos por préstamo.

### Paso 3. Configurar la conexión

Abrir el archivo:

`app/config/config.php`

Verificar que los datos de conexión correspondan con la configuración local de MySQL o MariaDB:

- Servidor: `localhost`.
- Usuario: `root` (si se utiliza la configuración predeterminada de XAMPP).
- Contraseña: la configurada en el entorno local.
- Base de datos: `sigel`.
- Puerto: `3306` (si corresponde).

### Paso 4. Ejecutar el proyecto

Iniciar Apache y MySQL desde XAMPP y abrir el proyecto mediante la dirección local configurada.

Si se utiliza el VirtualHost del proyecto:

```text
http://sigel.test
```

## 8. Seguridad

SIGEL implementa diferentes mecanismos de seguridad:

- Contraseñas protegidas mediante `password_hash()` y verificadas con `password_verify()`.
- Control de acceso mediante roles y permisos.
- Protección CSRF en formularios y operaciones que modifican información.
- Consultas preparadas mediante PDO.
- Validación de datos recibidos desde formularios.
- Registro de actividades mediante la bitácora del sistema.

## 9. Estructura general del proyecto

```text
SIGEL/
│
├── app/
│   ├── config/
│   │   └── config.php
│   │
│   └── includes/
│       ├── auth.php
│       └── bitacora.php
│
├── public/
│   ├── css/
│   │   └── estilos.css
│   │
│   ├── usuarios/
│   ├── categorias/
│   ├── equipos/
│   ├── prestamos/
│   ├── reportes/
│   ├── bitacora/
│   │
│   ├── dashboard.php
│   ├── login.php
│   ├── procesar_login.php
│   └── logout.php
│
└── README.md
```

*La estructura muestra los directorios y archivos principales del proyecto; pueden existir otros archivos adicionales.*

## 10. Usuarios de prueba

El sistema cuenta con los siguientes usuarios iniciales para realizar pruebas:

| Rol | Usuario | Contraseña |
|---|---|---|
| Administrador | admin@gmail.com | 12345678 |
| Operador | operador@gmail.com | 12345678 |
