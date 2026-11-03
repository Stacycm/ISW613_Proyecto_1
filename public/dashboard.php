<?php

require_once __DIR__ . '/../app/includes/auth.php';

requiereLogin();

// Obtener datos de sesión
$nombre = $_SESSION['nombre'];
$rol_id = (int) $_SESSION['rol_id'];

// Crear token CSRF para cerrar sesión
if (empty($_SESSION['csrf_token_logout'])) {
    $_SESSION['csrf_token_logout'] = bin2hex(random_bytes(32));
}

// Identificar rol
if ($rol_id === 1) {
    $rol = 'Administrador';
} elseif ($rol_id === 2) {
    $rol = 'Operador';
} else {
    $rol = 'Rol desconocido';
}

// Función para escapar texto
function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/css/estilos.css">

    <title>Panel principal | SIGEL</title>
</head>

<body>

    <header class="encabezado">
        <div class="encabezado-contenido">
            <h1>SIGEL</h1>
            <p>Sistema de Gestión de Equipos y Préstamos</p>
        </div>

        <form action="/logout.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapar($_SESSION['csrf_token_logout']) ?>"
            >

            <button type="submit" class="boton-salir">
                Cerrar sesión
            </button>

        </form>
    </header>

    <main class="contenedor-dashboard">

        <section class="bienvenida">

            <div>
                <h2>¡Bienvenido, <?= escapar($nombre) ?>!</h2>

                <p>
                    Este es tu panel principal.
                    Desde aquí puedes acceder a las funciones del sistema.
                </p>
            </div>

            <span class="etiqueta-rol">
                <?= escapar($rol) ?>
            </span>

        </section>

        <?php if ($rol_id === 1): ?>

            <section class="seccion-modulos">

                <h2>Administración</h2>

                <p class="descripcion-seccion">
                    Gestiona los recursos y la información del sistema.
                </p>

                <div class="tarjetas">

                    <?php if (tienePermiso('usuario_consultar')): ?>
                        <a href="/usuarios/listar.php" class="tarjeta">
                            <span class="icono-tarjeta">👥</span>
                            <h3>Usuarios</h3>
                            <p>Consultar y administrar usuarios del sistema.</p>
                        </a>
                    <?php endif; ?>

                    <?php if (tienePermiso('categoria_consultar')): ?>
                        <a href="/categorias/listar.php" class="tarjeta">
                            <span class="icono-tarjeta">📁</span>
                            <h3>Categorías</h3>
                            <p>Organizar las categorías de los equipos.</p>
                        </a>
                    <?php endif; ?>

                    <?php if (tienePermiso('equipo_consultar')): ?>
                        <a href="/equipos/listar.php" class="tarjeta">
                            <span class="icono-tarjeta">💻</span>
                            <h3>Equipos</h3>
                            <p>Consultar y administrar los equipos disponibles.</p>
                        </a>
                    <?php endif; ?>

                    <?php if (tienePermiso('prestamo_consultar')): ?>
                        <a href="/prestamos/listar.php" class="tarjeta">
                            <span class="icono-tarjeta">📋</span>
                            <h3>Préstamos</h3>
                            <p>Consultar y gestionar los préstamos.</p>
                        </a>
                    <?php endif; ?>

                    <?php if (tienePermiso('reporte_consultar')): ?>
                        <a href="/reportes/listar.php" class="tarjeta">
                            <span class="icono-tarjeta">📊</span>
                            <h3>Reportes</h3>
                            <p>Consultar y generar reportes del sistema.</p>
                        </a>
                    <?php endif; ?>

                    <?php if (tienePermiso('bitacora_consultar')): ?>
                        <a href="/bitacora/listar.php" class="tarjeta">
                            <span class="icono-tarjeta">📝</span>
                            <h3>Bitácora</h3>
                            <p>Consultar el registro de actividades.</p>
                        </a>
                    <?php endif; ?>

                </div>

            </section>

        <?php elseif ($rol_id === 2): ?>

            <section class="seccion-modulos">

                <h2>Operaciones</h2>

                <p class="descripcion-seccion">
                    Accede a las funciones de consulta y gestión de préstamos.
                </p>

                <div class="tarjetas">

                    <?php if (tienePermiso('prestamo_consultar')): ?>
                        <a href="/prestamos/listar.php" class="tarjeta">
                            <span class="icono-tarjeta">📋</span>
                            <h3>Préstamos</h3>
                            <p>Consultar y gestionar solicitudes de préstamo.</p>
                        </a>
                    <?php endif; ?>

                    <?php if (tienePermiso('equipo_consultar')): ?>
                        <a href="/equipos/listar.php" class="tarjeta">
                            <span class="icono-tarjeta">💻</span>
                            <h3>Consultar equipos</h3>
                            <p>Revisar los equipos registrados en el sistema.</p>
                        </a>
                    <?php endif; ?>

                </div>

            </section>

        <?php endif; ?>

        <footer class="pie-dashboard">
            <p>SIGEL &copy; <?= date('Y') ?> - Sistema de Gestión de Equipos y Préstamos</p>
        </footer>

    </main>

</body>
</html>
