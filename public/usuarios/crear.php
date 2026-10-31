<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

// Verificar que haya iniciado sesión
requiereLogin();

// Verificar permiso para crear usuarios
if (!tienePermiso('usuario_crear')) {
    http_response_code(403);
    exit('No tienes permiso para crear usuarios.');
}

// Conectar a la base de datos
$pdo = require __DIR__ . '/../../app/config/config.php';

// Variables para mensajes
$mensaje = '';
$error = '';

// Si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Comprobar el token CSRF
    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }

    // Obtener los datos del formulario
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol_id = (int) ($_POST['rol_id'] ?? 0);

    // Validar los datos
    if ($nombre === '' || $correo === '' || $password === '') {
        $error = 'Todos los campos son obligatorios.';

    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido.';

    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';

    } elseif (!in_array($rol_id, [1, 2], true)) {
        $error = 'El rol seleccionado no es válido.';

    } else {

        // Generar el hash de la contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {

            // Iniciar una transacción
            $pdo->beginTransaction();

            // Insertar el usuario
            $sql = "INSERT INTO usuarios
                    (rol_id, nombre, correo, password_hash, activo)
                    VALUES
                    (:rol_id, :nombre, :correo, :password_hash, 1)";

            $consulta = $pdo->prepare($sql);

            $consulta->execute([
                'rol_id' => $rol_id,
                'nombre' => $nombre,
                'correo' => $correo,
                'password_hash' => $password_hash
            ]);

            // Obtener el ID del usuario recién creado
            $usuarioId = (int) $pdo->lastInsertId();

            // Registrar la acción en la bitácora
            registrarBitacora(
                $pdo,
                'CREAR',
                'usuarios',
                $usuarioId,
                null,
                [
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'rol_id' => $rol_id
                ]
            );

            // Confirmar ambas operaciones
            $pdo->commit();

            // Eliminar el token utilizado
            unset($_SESSION['csrf_token']);

            // Redirigir al listado
            header('Location: listar.php?creado=1');
            exit;

        } catch (Throwable $e) {

            // Deshacer los cambios si ocurrió un error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            if ($e instanceof PDOException && $e->getCode() === '23000') {
                $error = 'Ese correo electrónico ya está registrado.';
            } else {
                http_response_code(500);
                $error = 'No fue posible registrar el usuario.';
            }
        }
    }
}

// Crear token CSRF si no existe
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

    <h1>Registrar usuario</h1>

    <?php if ($error !== ''): ?>
        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="POST" action="crear.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="nombre">Nombre:</label>
        <br>

        <input
            type="text"
            id="nombre"
            name="nombre"
            value="<?= htmlspecialchars($_POST['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <br><br>

        <label for="correo">Correo electrónico:</label>
        <br>

        <input
            type="email"
            id="correo"
            name="correo"
            value="<?= htmlspecialchars($_POST['correo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <br><br>

        <label for="password">Contraseña:</label>
        <br>

        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            required
        >

        <br><br>

        <label for="rol_id">Rol:</label>
        <br>

        <select id="rol_id" name="rol_id" required>

            <option value="">Seleccione un rol</option>

            <option value="1">Administrador</option>

            <option value="2">Operador</option>

        </select>

        <br><br>

        <button type="submit">Registrar usuario</button>

    </form>

    <br>

    <a href="listar.php">Volver a usuarios</a>

</body>

</html>


