<?php

require_once __DIR__ . '/../app/includes/auth.php';

requiereLogin();

$nombre = $_SESSION['nombre'];
$rol_id = $_SESSION['rol_id'];

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
        <strong><?= htmlspecialchars($nombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
    </p>

    <p>
        Rol: <strong><?= htmlspecialchars($rol, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
    </p>

    <?php if (tienePermiso('usuario_crear')): ?>

        <p>✅ Tienes permiso para crear usuarios.</p>

    <?php else: ?>

        <p>❌ No tienes permiso para crear usuarios.</p>

    <?php endif; ?>

    <hr>

    <?php if ($rol_id === 1): ?>

        <h3>Administración</h3>

        <ul>
            <li>Usuarios</li>
            <li>Categorías</li>
            <li>Equipos</li>
            <li>Reportes</li>
        </ul>

    <?php elseif ($rol_id === 2): ?>

        <h3>Operaciones</h3>

        <ul>
            <li>Préstamos</li>
            <li>Consultar equipos</li>
        </ul>

    <?php endif; ?>

    <hr>

    <p>
        <a href="logout.php">Cerrar sesión</a>
    </p>

</body>

</html>