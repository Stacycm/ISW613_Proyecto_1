<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

requiereLogin();

if (!tienePermiso('prestamo_gestionar_solicitudes')) {
    http_response_code(403);
    exit('No tienes permiso para gestionar préstamos.');
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

// Validar CSRF
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Solicitud no válida.');
}

// Validar ID
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit('ID de préstamo no válido.');
}

$pdo = require __DIR__ . '/../../app/config/config.php';

try {

    // Iniciar transacción
    $pdo->beginTransaction();

    // Consultar y bloquear el préstamo
    $sql = "SELECT id, estado, operador_id
            FROM prestamos
            WHERE id = :id
            FOR UPDATE";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    $prestamo = $consulta->fetch();

    if (!$prestamo) {
        throw new RuntimeException('El préstamo no existe.');
    }

    if ($prestamo['estado'] !== 'solicitado') {
        throw new RuntimeException(
            'Solo se pueden aprobar solicitudes pendientes.'
        );
    }

    // Determinar el operador asignado
    $operador_id = (int) $_SESSION['rol_id'] === 2
        ? (int) $_SESSION['id']
        : null;

    // Actualizar el estado del préstamo
    $sql = "UPDATE prestamos
            SET estado = 'aprobado',
                operador_id = :operador_id
            WHERE id = :id
            AND estado = 'solicitado'";

    $consulta = $pdo->prepare($sql);

    $consulta->execute([
        'operador_id' => $operador_id,
        'id' => $id
    ]);

    if ($consulta->rowCount() === 0) {
        throw new RuntimeException(
            'No se pudo aprobar el préstamo.'
        );
    }

    // Guardar los valores anteriores
    $valoresAntes = [
        'estado' => $prestamo['estado'],
        'operador_id' => $prestamo['operador_id'] !== null
            ? (int) $prestamo['operador_id']
            : null
    ];

    // Guardar los valores nuevos
    $valoresDespues = [
        'estado' => 'aprobado',
        'operador_id' => $operador_id
    ];

    // Registrar la aprobación en la bitácora
    registrarBitacora(
        $pdo,
        'APROBAR',
        'prestamos',
        (int) $id,
        $valoresAntes,
        $valoresDespues
    );

    // Confirmar la transacción
    $pdo->commit();

    // Renovar token CSRF
    unset($_SESSION['csrf_token']);

    header('Location: listar.php?aprobado=1');
    exit;

} catch (Throwable $e) {

    // Deshacer los cambios si ocurre un error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof RuntimeException) {
        http_response_code(400);
        exit(
            htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )
        );
    }

    error_log($e->getMessage());

    http_response_code(500);
    exit('Ocurrió un error al aprobar el préstamo.');
}
