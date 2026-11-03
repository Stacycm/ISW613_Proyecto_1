<?php

require_once __DIR__ . '/../app/includes/auth.php';

// Si ya inició sesión, enviarlo al dashboard
if (isset($_SESSION['id'])) {
    header('Location: /dashboard.php');
    exit;
}

// Crear token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/css/estilos.css">

    <title>Iniciar sesión | SIGEL</title>
</head>

<body class="pagina-login">

    <main class="login-contenedor">

        <section class="login-tarjeta">

            <div class="login-logo">
                <span>📋</span>
            </div>

            <h1>SIGEL</h1>

            <p class="login-subtitulo">
                Sistema de Gestión de Equipos y Préstamos
            </p>

            <div class="login-separador"></div>

            <h2>Iniciar sesión</h2>

            <p class="login-descripcion">
                Ingresa tus credenciales para continuar.
            </p>

            <?php if (isset($_GET['error'])): ?>

                <div class="mensaje mensaje-error">
                    Correo o contraseña incorrectos.
                </div>

            <?php endif; ?>

            <form action="/procesar_login.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>"
                >

                <div class="grupo-login">

                    <label for="correo">Correo electrónico</label>

                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        placeholder="ejemplo@correo.com"
                        autocomplete="username"
                        required
                    >

                </div>

                <div class="grupo-login">

                    <label for="password">Contraseña</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Ingresa tu contraseña"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <div class="recordarme">

                    <input
                        type="checkbox"
                        id="recordarme"
                        name="recordarme"
                        value="1"
                    >

                    <label for="recordarme">Recordarme</label>

                </div>

                <button type="submit" class="boton-login">
                    Ingresar
                </button>

            </form>

        </section>

        <footer class="login-footer">
            SIGEL &copy; <?= date('Y') ?> - Sistema de Gestión de Equipos y Préstamos
        </footer>

    </main>

</body>
</html>
