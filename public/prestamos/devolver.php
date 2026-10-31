<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

requiereLogin();

// Verificar permiso
if (!tienePermiso('prestamo_devoluciones')) {
    http_response_code(403);
    exit('No tienes permiso para registrar devoluciones.');
}

// Solo permitir POST
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

// Validar IDs
$prestamo_id = filter_var(
    $_POST['prestamo_id'] ?? null,
    FILTER_VALIDATE_INT
);

$equipo_id = filter_var(
    $_POST['equipo_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    !$prestamo_id || $prestamo_id < 1 ||
    !$equipo_id || $equipo_id < 1
) {
    exit('Datos no válidos.');
}

// Conexión
$pdo = require __DIR__ . '/../../app/config/config.php';

try {

    // Iniciar transacción
    $pdo->beginTransaction();

    // Buscar y bloquear el préstamo
    $sql = "SELECT id, estado
            FROM prestamos
            WHERE id = :id
            FOR UPDATE";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $prestamo_id]);

    $prestamo = $consulta->fetch();

    if (!$prestamo) {
        throw new RuntimeException('El préstamo no existe.');
    }

    if ($prestamo['estado'] !== 'entregado') {
        throw new RuntimeException(
            'El préstamo no está en estado entregado.'
        );
    }

    // Verificar que el equipo pertenezca al préstamo
    $sql = "SELECT equipo_id, fecha_devolucion
            FROM prestamo_detalle
            WHERE prestamo_id = :prestamo_id
            AND equipo_id = :equipo_id
            FOR UPDATE";

    $consulta = $pdo->prepare($sql);
    $consulta->execute([
        'prestamo_id' => $prestamo_id,
        'equipo_id' => $equipo_id
    ]);

    $detalle = $consulta->fetch();

    if (!$detalle) {
        throw new RuntimeException(
            'El equipo no pertenece a este préstamo.'
        );
    }

    if ($detalle['fecha_devolucion'] !== null) {
        throw new RuntimeException('Este equipo ya fue devuelto.');
    }

    // Bloquear el equipo
    $sql = "SELECT id, codigo, nombre, estado
            FROM equipos
            WHERE id = :id
            AND eliminado_en IS NULL
            FOR UPDATE";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $equipo_id]);

    $equipo = $consulta->fetch();

    if (!$equipo || $equipo['estado'] !== 'Prestado') {
        throw new RuntimeException(
            'El equipo no se encuentra registrado como prestado.'
        );
    }

    // Guardar los valores anteriores
    $valoresAntes = [
        'prestamo_id' => (int) $prestamo_id,
        'estado_prestamo' => $prestamo['estado'],
        'equipo_id' => (int) $equipo_id,
        'codigo_equipo' => $equipo['codigo'],
        'nombre_equipo' => $equipo['nombre'],
        'estado_equipo' => $equipo['estado'],
        'fecha_devolucion' => null
    ];

    // Registrar la fecha de devolución
    $sql = "UPDATE prestamo_detalle
            SET fecha_devolucion = CURDATE()
            WHERE prestamo_id = :prestamo_id
            AND equipo_id = :equipo_id
            AND fecha_devolucion IS NULL";

    $consulta = $pdo->prepare($sql);
    $consulta->execute([
        'prestamo_id' => $prestamo_id,
        'equipo_id' => $equipo_id
    ]);

    if ($consulta->rowCount() !== 1) {
        throw new RuntimeException(
            'No se pudo registrar la devolución.'
        );
    }

    // Obtener la fecha registrada
    $sqlFecha = "SELECT fecha_devolucion
                 FROM prestamo_detalle
                 WHERE prestamo_id = :prestamo_id
                 AND equipo_id = :equipo_id";

    $consultaFecha = $pdo->prepare($sqlFecha);
    $consultaFecha->execute([
        'prestamo_id' => $prestamo_id,
        'equipo_id' => $equipo_id
    ]);

    $fechaDevolucion = $consultaFecha->fetchColumn();

    // Cambiar el equipo a Disponible
    $sql = "UPDATE equipos
            SET estado = 'Disponible',
                version = version + 1
            WHERE id = :id
            AND estado = 'Prestado'";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $equipo_id]);

    if ($consulta->rowCount() !== 1) {
        throw new RuntimeException(
            'No se pudo actualizar el estado del equipo.'
        );
    }

    // Comprobar si quedan equipos pendientes de devolución
    $sql = "SELECT COUNT(*)
            FROM prestamo_detalle
            WHERE prestamo_id = :id
            AND fecha_devolucion IS NULL";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $prestamo_id]);

    $pendientes = (int) $consulta->fetchColumn();

    // Determinar el estado final del préstamo
    $nuevoEstadoPrestamo = 'entregado';

    // Si todos fueron devueltos, cerrar el préstamo
    if ($pendientes === 0) {

        $sql = "UPDATE prestamos
                SET estado = 'devuelto'
                WHERE id = :id
                AND estado = 'entregado'";

        $consulta = $pdo->prepare($sql);
        $consulta->execute(['id' => $prestamo_id]);

        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException(
                'No se pudo cerrar el préstamo.'
            );
        }

        $nuevoEstadoPrestamo = 'devuelto';
    }

    // Guardar los valores nuevos
    $valoresDespues = [
        'prestamo_id' => (int) $prestamo_id,
        'estado_prestamo' => $nuevoEstadoPrestamo,
        'equipo_id' => (int) $equipo_id,
        'codigo_equipo' => $equipo['codigo'],
        'nombre_equipo' => $equipo['nombre'],
        'estado_equipo' => 'Disponible',
        'fecha_devolucion' => $fechaDevolucion,
        'equipos_pendientes' => $pendientes
    ];

    // Registrar la devolución en la bitácora
    registrarBitacora(
        $pdo,
        'DEVOLVER',
        'prestamos',
        (int) $prestamo_id,
        $valoresAntes,
        $valoresDespues
    );

    // Confirmar todas las operaciones
    $pdo->commit();

    unset($_SESSION['csrf_token']);

    header('Location: listar.php?devuelto=1');
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
    exit('Ocurrió un error al registrar la devolución.');
}
