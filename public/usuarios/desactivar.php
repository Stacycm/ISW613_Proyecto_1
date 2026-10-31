
<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

// Verificar que haya iniciado sesión
requiereLogin();

// Verificar permiso para desactivar usuarios
if (!tienePermiso('usuario_desactivar')) {
    http_response_code(403);
    exit('No tienes permiso para desactivar usuarios.');
}

// Aceptar solamente solicitudes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

// Comprobar el token CSRF
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Solicitud no válida.');
}

// Obtener el ID del usuario
$id = (int) ($_POST['id'] ?? 0);

// Validar el ID
if ($id <= 0) {
    exit('Usuario no válido.');
}

// Evitar que el usuario desactive su propia cuenta
if ($id === (int) $_SESSION['id']) {
    exit('No puedes desactivar tu propia cuenta.');
}

try {

    // Conectar a la base de datos
    $pdo = require __DIR__ . '/../../app/config/config.php';

    // Iniciar una transacción
    $pdo->beginTransaction();

    // Buscar el usuario y bloquear el registro
    $sql = "SELECT id, nombre, correo, rol_id, activo
            FROM usuarios
            WHERE id = :id
            FOR UPDATE";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    $usuarioAnterior = $consulta->fetch();

    // Comprobar que el usuario exista
    if (!$usuarioAnterior) {
        throw new RuntimeException('El usuario no existe.');
    }

    // Comprobar que esté activo
    if ((int) $usuarioAnterior['activo'] !== 1) {
        throw new RuntimeException('El usuario ya está desactivado.');
    }

    // Guardar los valores anteriores
    $valoresAntes = [
        'nombre' => $usuarioAnterior['nombre'],
        'correo' => $usuarioAnterior['correo'],
        'rol_id' => $usuarioAnterior['rol_id'],
        'activo' => $usuarioAnterior['activo']
    ];

    // Desactivar el usuario
    $sql = "UPDATE usuarios
            SET activo = 0
            WHERE id = :id AND activo = 1";

    $consulta = $pdo->prepare($sql);

    $consulta->execute([
        'id' => $id
    ]);

    // Guardar los valores nuevos
    $valoresDespues = [
        'nombre' => $usuarioAnterior['nombre'],
        'correo' => $usuarioAnterior['correo'],
        'rol_id' => $usuarioAnterior['rol_id'],
        'activo' => 0
    ];

    // Registrar la acción en la bitácora
    registrarBitacora(
        $pdo,
        'DESACTIVAR',
        'usuarios',
        $id,
        $valoresAntes,
        $valoresDespues
    );

    // Confirmar los cambios
    $pdo->commit();

    // Eliminar el token utilizado
    unset($_SESSION['csrf_token']);

    // Redirigir al listado
    header('Location: listar.php?desactivado=1');
    exit;

} catch (Throwable $e) {

    // Deshacer los cambios si ocurrió un error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    if ($e instanceof RuntimeException) {
        exit($e->getMessage());
    }

    http_response_code(500);
    exit('No fue posible desactivar el usuario.');
}
