<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

requiereLogin();

if (!tienePermiso('equipo_eliminar')) {
    http_response_code(403);
    exit('No tienes permiso para eliminar equipos.');
}

// Solo aceptar solicitudes POST
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
    exit('ID de equipo no válido.');
}

$pdo = require __DIR__ . '/../../app/config/config.php';

try {

    // Iniciar transacción
    $pdo->beginTransaction();

    // Comprobar que el equipo exista y bloquearlo
    $sql = "SELECT id, codigo, nombre, categoria_id, estado, eliminado_en
            FROM equipos
            WHERE id = :id
            AND eliminado_en IS NULL
            FOR UPDATE";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    $equipo = $consulta->fetch();

    if (!$equipo) {
        throw new RuntimeException('El equipo no existe o ya fue eliminado.');
    }

    // Comprobar si el equipo tiene préstamos asociados
    $sqlPrestamos = "SELECT COUNT(*)
                     FROM prestamo_detalle
                     WHERE equipo_id = :id";

    $consultaPrestamos = $pdo->prepare($sqlPrestamos);
    $consultaPrestamos->execute(['id' => $id]);

    $tienePrestamos = (int) $consultaPrestamos->fetchColumn();

    if ($tienePrestamos > 0) {
        throw new RuntimeException(
            'No se puede eliminar el equipo porque tiene préstamos asociados.'
        );
    }

    // Guardar los valores anteriores
    $valoresAntes = [
        'codigo' => $equipo['codigo'],
        'nombre' => $equipo['nombre'],
        'categoria_id' => (int) $equipo['categoria_id'],
        'estado' => $equipo['estado'],
        'eliminado_en' => $equipo['eliminado_en']
    ];

    // Eliminar lógicamente el equipo
    $sqlEliminar = "UPDATE equipos
                    SET eliminado_en = NOW(),
                        version = version + 1
                    WHERE id = :id
                    AND eliminado_en IS NULL";

    $consultaEliminar = $pdo->prepare($sqlEliminar);
    $consultaEliminar->execute(['id' => $id]);

    if ($consultaEliminar->rowCount() === 0) {
        throw new RuntimeException('No se pudo eliminar el equipo.');
    }

    // Guardar los valores posteriores
    $consultaFecha = $pdo->query("SELECT eliminado_en FROM equipos WHERE id = " . (int) $id);

    $fechaEliminacion = $consultaFecha->fetchColumn();

    $valoresDespues = [
        'codigo' => $equipo['codigo'],
        'nombre' => $equipo['nombre'],
        'categoria_id' => (int) $equipo['categoria_id'],
        'estado' => $equipo['estado'],
        'eliminado_en' => $fechaEliminacion
    ];

    // Registrar la eliminación en la bitácora
    registrarBitacora(
        $pdo,
        'ELIMINAR',
        'equipos',
        (int) $id,
        $valoresAntes,
        $valoresDespues
    );

    // Confirmar la transacción
    $pdo->commit();

    unset($_SESSION['csrf_token']);

    header('Location: listar.php?eliminado=1');
    exit;

} catch (Throwable $e) {

    // Deshacer los cambios si ocurre un error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e instanceof RuntimeException) {
        http_response_code(400);
        exit($e->getMessage());
    }

    error_log($e->getMessage());

    http_response_code(500);
    exit('Ocurrió un error al eliminar el equipo.');
}
