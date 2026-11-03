<?php

require_once __DIR__ . '/../app/includes/auth.php';

requiereLogin();

// Obtener datos de sesión.
$nombre = $_SESSION['nombre'];
$rol_id = (int) $_SESSION['rol_id'];

// Crear token CSRF para cerrar sesión.
if (empty($_SESSION['csrf_token_logout'])) {
    $_SESSION['csrf_token_logout'] = bin2hex(random_bytes(32));
}

// Identificar rol.
if ($rol_id === 1) {
    $rol = 'Administrador';
} elseif ($rol_id === 2) {
    $rol = 'Operador';
} else {
    $rol = 'Rol desconocido';
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | SIGEL</title>
</head>

<body>

    <h1>SIGEL</h1>

    <h2>Panel principal</h2>

    <p>
        Bienvenido,
        <strong>
            <?= htmlspecialchars(
                $nombre,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>
        </strong>
    </p>

    <p>
        Rol:
        <strong>
            <?= htmlspecialchars(
                $rol,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>
        </strong>
    </p>

    <hr>

    <?php if ($rol_id === 1): ?>

        <h3>Administración</h3>

        <ul>

            <?php if (tienePermiso('usuario_consultar')): ?>
                <li><a href="/usuarios/listar.php">Usuarios</a></li>
            <?php endif; ?>

            <?php if (tienePermiso('categoria_consultar')): ?>
                <li><a href="/categorias/listar.php">Categorías</a></li>
            <?php endif; ?>

            <?php if (tienePermiso('equipo_consultar')): ?>
                <li><a href="/equipos/listar.php">Equipos</a></li>
            <?php endif; ?>

            <?php if (tienePermiso('reporte_consultar')): ?>
                <li><a href="/reportes/listar.php">Reportes</a></li>
            <?php endif; ?>

            <?php if (tienePermiso('prestamo_consultar')): ?>
                <li><a href="/prestamos/listar.php">Préstamos</a></li>
            <?php endif; ?>

            <?php if (tienePermiso('bitacora_consultar')): ?>
                <li><a href="/bitacora/listar.php">Bitácora</a></li>
            <?php endif; ?>

        </ul>

    <?php elseif ($rol_id === 2): ?>

        <h3>Operaciones</h3>

        <ul>

            <?php if (tienePermiso('prestamo_consultar')): ?>
                <li><a href="/prestamos/listar.php">Préstamos</a></li>
            <?php endif; ?>

            <?php if (tienePermiso('equipo_consultar')): ?>
                <li><a href="/equipos/listar.php">Consultar equipos</a></li>
            <?php endif; ?>

        </ul>

    <?php endif; ?>

    <hr>

    <form action="/logout.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $_SESSION['csrf_token_logout'],
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>"
        >

        <button type="submit">Cerrar sesión</button>

    </form>

</body>
</html>

