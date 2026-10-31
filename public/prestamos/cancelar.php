<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

requiereLogin();

// Verificar permiso
if (!tienePermiso('prestamo_gestionar_solicitudes')) {
    http_response_code(403);
    exit('No tienes permiso para cancelar préstamos.');
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

// Verificar CSRF
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

// Conexión
$pdo = require __DIR__ . '/../../app/config/config.php';

try {

    // Iniciar transacción
    $pdo->beginTransaction();

    // Buscar y bloquear el préstamo
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

    // Solo cancelar solicitudes o préstamos aprobados
    if (!in_array($prestamo['estado'], ['solicitado', 'aprobado'], true)) {
        throw new RuntimeException('Este préstamo no se puede cancelar.');
    }

    // Guardar los valores anteriores
    $valoresAntes = [
        'estado' => $prestamo['estado'],
        'operador_id' => $prestamo['operador_id'] !== null
            ? (int) $prestamo['operador_id']
            : null
    ];

    // Actualizar estado
    $sql = "UPDATE prestamos
            SET estado = 'cancelado'
            WHERE id = :id
            AND estado IN ('solicitado', 'aprobado')";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    if ($consulta->rowCount() !== 1) {
        throw new RuntimeException('No se pudo cancelar el préstamo.');
    }

    // Guardar los valores nuevos
    $valoresDespues = [
        'estado' => 'cancelado',
        'operador_id' => $valoresAntes['operador_id']
    ];

    // Registrar la cancelación en la bitácora
    registrarBitacora(
        $pdo,
        'CANCELAR',
        'prestamos',
        (int) $id,
        $valoresAntes,
        $valoresDespues
    );

    // Confirmar transacción
    $pdo->commit();

    // Renovar token CSRF
    unset($_SESSION['csrf_token']);

    header('Location: listar.php?cancelado=1');
    exit;

} catch (Throwable $e) {

    // Deshacer cambios si ocurre un error
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
    exit('Ocurrió un error al cancelar el préstamo.');
}
