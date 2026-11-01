<?php

require_once __DIR__ . '/../app/includes/auth.php';

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
    <title>Iniciar sesión | SIGEL</title>
</head>
<body>

    <h1>SIGEL</h1>
    <h2>Iniciar sesión</h2>

    <?php if (isset($_GET['error'])): ?>
        <p>Correo o contraseña incorrectos.</p>
    <?php endif; ?>

    <form action="procesar_login.php" method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $_SESSION['csrf_token'],
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>"
        >

        <label for="correo">Correo electrónico:</label>
        <input
            type="email"
            id="correo"
            name="correo"
            required
        >

        <br><br>

        <label for="password">Contraseña:</label>
        <input
            type="password"
            id="password"
            name="password"
            required
        >

        <br><br>

        <label for="recordarme">
            <input
                type="checkbox"
                id="recordarme"
                name="recordarme"
                value="1"
            >
            Recordarme
        </label>

        <br><br>

        <button type="submit">Ingresar</button>

    </form>

</body>
</html>
