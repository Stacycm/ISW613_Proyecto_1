<?php

require_once __DIR__ . '/../../app/includes/auth.php';

// Comprobar que haya iniciado sesión
requiereLogin();

// Comprobar que tenga permiso para crear usuarios
if (!tienePermiso('usuario_crear')) {
    http_response_code(403);
    exit('No tienes permiso para acceder a esta página.');
}

// Crear un token de seguridad para el formulario
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear usuario | SIGEL</title>
</head>

<body>

    <h1>SIGEL</h1>
    <h2>Registrar nuevo usuario</h2>

    <?php if (isset($_GET['exito'])): ?>
        <p>¡Usuario registrado correctamente!</p>
    <?php endif; ?>

    <form action="guardar.php" method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <label for="nombre">Nombre completo:</label>
        <input type="text" id="nombre" name="nombre" required>

        <br><br>

        <label for="correo">Correo electrónico:</label>
        <input type="email" id="correo" name="correo" required>

        <br><br>

        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password"
               minlength="8" required>

        <br><br>

        <label for="rol_id">Rol:</label>
        <select id="rol_id" name="rol_id" required>
            <option value="">Seleccione un rol</option>
            <option value="1">Administrador</option>
            <option value="2">Operador</option>
        </select>

        <br><br>

        <button type="submit">Registrar usuario</button>

    </form>

    <p><a href="../dashboard.php">Volver al panel principal</a></p>

</body>
</html>

