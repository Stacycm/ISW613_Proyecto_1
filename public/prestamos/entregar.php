<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

requiereLogin();

// Verificar permiso
if (!tienePermiso('prestamo_gestionar_solicitudes')) {
    http_response_code(403);
    exit('No tienes permiso para entregar préstamos.');
}

// Solo permitir solicitudes POST
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
    $sql = "SELECT id, estado
            FROM prestamos
            WHERE id = :id
            FOR UPDATE";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    $prestamo = $consulta->fetch();

    if (!$prestamo) {
        throw new RuntimeException('El préstamo no existe.');
    }

    // Verificar que esté aprobado
    if ($prestamo['estado'] !== 'aprobado') {
        throw new RuntimeException(
            'Solo se pueden entregar préstamos aprobados.'
        );
    }

    // Obtener los equipos del préstamo
    $sql = "SELECT equipo_id
            FROM prestamo_detalle
            WHERE prestamo_id = :id
            ORDER BY equipo_id";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    $equipos = $consulta->fetchAll(PDO::FETCH_COLUMN);

    if (empty($equipos)) {
        throw new RuntimeException(
            'El préstamo no tiene equipos registrados.'
        );
    }

    // Bloquear los equipos
    $marcadores = implode(',', array_fill(0, count($equipos), '?'));

    $sql = "SELECT id, codigo, nombre, estado
            FROM equipos
            WHERE id IN ($marcadores)
            AND eliminado_en IS NULL
            ORDER BY id
            FOR UPDATE";

    $consulta = $pdo->prepare($sql);
    $consulta->execute($equipos);

    $equiposBloqueados = $consulta->fetchAll();

    if (count($equiposBloqueados) !== count($equipos)) {
        throw new RuntimeException(
            'Uno o más equipos no están disponibles.'
        );
    }

    // Comprobar que todos estén disponibles
    foreach ($equiposBloqueados as $equipo) {

        if ($equipo['estado'] !== 'Disponible') {
            throw new RuntimeException(
                'Uno o más equipos ya no están disponibles.'
            );
        }
    }

    // Guardar información anterior de los equipos
    $equiposAntes = [];

    foreach ($equiposBloqueados as $equipo) {
        $equiposAntes[] = [
            'equipo_id' => (int) $equipo['id'],
            'codigo' => $equipo['codigo'],
            'nombre' => $equipo['nombre'],
            'estado' => $equipo['estado']
        ];
    }

    // Cambiar los equipos a Prestado
    $sql = "UPDATE equipos
            SET estado = 'Prestado',
                version = version + 1
            WHERE id IN ($marcadores)
            AND estado = 'Disponible'";

    $consulta = $pdo->prepare($sql);
    $consulta->execute($equipos);

    if ($consulta->rowCount() !== count($equipos)) {
        throw new RuntimeException(
            'No se pudieron actualizar todos los equipos.'
        );
    }

    // Cambiar el estado del préstamo
    $sql = "UPDATE prestamos
            SET estado = 'entregado'
            WHERE id = :id
            AND estado = 'aprobado'";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    if ($consulta->rowCount() !== 1) {
        throw new RuntimeException(
            'No se pudo actualizar el préstamo.'
        );
    }

    // Preparar información nueva de los equipos
    $equiposDespues = [];

    foreach ($equiposBloqueados as $equipo) {
        $equiposDespues[] = [
            'equipo_id' => (int) $equipo['id'],
            'codigo' => $equipo['codigo'],
            'nombre' => $equipo['nombre'],
            'estado' => 'Prestado'
        ];
    }

    // Registrar la entrega en la bitácora
    registrarBitacora(
        $pdo,
        'ENTREGAR',
        'prestamos',
        (int) $id,
        [
            'estado_prestamo' => 'aprobado',
            'equipos' => $equiposAntes
        ],
        [
            'estado_prestamo' => 'entregado',
            'equipos' => $equiposDespues
        ]
    );

    // Confirmar todas las operaciones
    $pdo->commit();

    unset($_SESSION['csrf_token']);

    header('Location: listar.php?entregado=1');
    exit;

} catch (Throwable $e) {

    // Deshacer cambios si ocurre un error
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
