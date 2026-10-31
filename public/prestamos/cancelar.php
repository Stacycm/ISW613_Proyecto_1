<?php

require_once __DIR__ . '/../../app/includes/auth.php';
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

    $pdo->beginTransaction();

    // Buscar y bloquear el préstamo
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

    // Solo cancelar solicitudes o préstamos aprobados
    if (!in_array($prestamo['estado'], ['solicitado', 'aprobado'], true)) {
        throw new Exception('Este préstamo no se puede cancelar.');
    }

    // Actualizar estado
    $sql = "UPDATE prestamos
            SET estado = 'cancelado'
            WHERE id = :id
            AND estado IN ('solicitado', 'aprobado')";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    if ($consulta->rowCount() !== 1) {
        throw new Exception('No se pudo cancelar el préstamo.');
    }

    $pdo->commit();

    unset($_SESSION['csrf_token']);

    header('Location: listar.php?cancelado=1');
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
