<?php

require_once __DIR__ . '/../../app/includes/auth.php';

requiereLogin();

// Verificar permiso para editar usuarios
if (!tienePermiso('usuario_editar')) {
    http_response_code(403);
    exit('No tienes permiso para editar usuarios.');
}

// Conectar a la base de datos
$pdo = require __DIR__ . '/../../app/config/config.php';

// Obtener el ID del usuario
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    exit('Usuario no válido.');
}

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

    // Obtener los datos
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $rol_id = (int) ($_POST['rol_id'] ?? 0);

    // Validar los datos
    if ($nombre === '' || $correo === '') {
        exit('Todos los campos son obligatorios.');
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        exit('El correo electrónico no es válido.');
    }

    if (!in_array($rol_id, [1, 2], true)) {
        exit('El rol seleccionado no es válido.');
    }

    try {

        // Actualizar el usuario
        $sql = "UPDATE usuarios
                SET nombre = :nombre,
                    correo = :correo,
                    rol_id = :rol_id
                WHERE id = :id";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'nombre' => $nombre,
            'correo' => $correo,
            'rol_id' => $rol_id,
            'id' => $id
        ]);

        // Eliminar el token utilizado
        unset($_SESSION['csrf_token']);

        // Redirigir al listado
        header('Location: listar.php?actualizado=1');
        exit;

    } catch (PDOException $e) {

        error_log($e->getMessage());

        if ($e->getCode() === '23000') {
            exit('Ese correo electrónico ya está registrado.');
        }

        http_response_code(500);
        exit('No fue posible actualizar el usuario.');
    }
}

// Buscar el usuario para mostrar sus datos
$sql = "SELECT id, nombre, correo, rol_id, activo
        FROM usuarios
        WHERE id = :id
        LIMIT 1";

$consulta = $pdo->prepare($sql);
$consulta->execute(['id' => $id]);

$usuario = $consulta->fetch();

if (!$usuario) {
    exit('El usuario no existe.');
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
    <title>Editar usuario | SIGEL</title>
</head>

<body>

    <h1>Editar usuario</h1>

    <form method="POST" action="editar.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <input
            type="hidden"
            name="id"
            value="<?= (int) $usuario['id'] ?>"
        >

        <label for="nombre">Nombre:</label>
        <br>

        <input
            type="text"
            id="nombre"
            name="nombre"
            value="<?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <br><br>

        <label for="correo">Correo:</label>
        <br>

        <input
            type="email"
            id="correo"
            name="correo"
            value="<?= htmlspecialchars($usuario['correo'], ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <br><br>

        <label for="rol_id">Rol:</label>
        <br>

        <select id="rol_id" name="rol_id" required>

            <option value="1"
                <?= $usuario['rol_id'] == 1 ? 'selected' : '' ?>>
                Administrador
            </option>

            <option value="2"
                <?= $usuario['rol_id'] == 2 ? 'selected' : '' ?>>
                Operador
            </option>

        </select>

        <br><br>

        <button type="submit">Guardar cambios</button>

    </form>

    <br>

    <a href="listar.php">Volver a usuarios</a>

</body>

</html>
