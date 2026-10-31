<?php

require_once __DIR__ . '/../../app/includes/auth.php';

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

    $pdo->beginTransaction();

    // Consultar y bloquear el préstamo
    $sql = "SELECT id, estado
            FROM prestamos
            WHERE id = :id
            FOR UPDATE";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    $prestamo = $consulta->fetch();

    if (!$prestamo) {
        throw new Exception('El préstamo no existe.');
    }

    if ($prestamo['estado'] !== 'solicitado') {
        throw new Exception('Solo se pueden aprobar solicitudes pendientes.');
    }

    // Actualizar estado
    $sql = "UPDATE prestamos
            SET estado = 'aprobado',
                operador_id = :operador_id
            WHERE id = :id
            AND estado = 'solicitado'";

    $consulta = $pdo->prepare($sql);

    $operador_id = (int) $_SESSION['rol_id'] === 2
        ? $_SESSION['id']
        : null;

    $consulta->execute([
        'operador_id' => $operador_id,
        'id' => $id
    ]);

    $pdo->commit();

    unset($_SESSION['csrf_token']);

    header('Location: listar.php?aprobado=1');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    http_response_code(400);
    exit(
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        )
    );
}
