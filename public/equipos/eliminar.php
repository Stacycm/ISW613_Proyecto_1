<?php

require_once __DIR__ . '/../../app/includes/auth.php';

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

    // Comprobar que el equipo exista y esté activo
    $sql = "SELECT id, estado
            FROM equipos
            WHERE id = :id
            AND eliminado_en IS NULL";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    $equipo = $consulta->fetch();

    if (!$equipo) {
        exit('El equipo no existe o ya fue eliminado.');
    }

    // Comprobar si el equipo tiene préstamos asociados
    $sqlPrestamos = "SELECT COUNT(*)
                     FROM prestamo_detalle
                     WHERE equipo_id = :id";

    $consultaPrestamos = $pdo->prepare($sqlPrestamos);
    $consultaPrestamos->execute(['id' => $id]);

    $tienePrestamos = (int) $consultaPrestamos->fetchColumn();

    if ($tienePrestamos > 0) {
        exit('No se puede eliminar el equipo porque tiene préstamos asociados.');
    }

    // Eliminar lógicamente el equipo
    $sqlEliminar = "UPDATE equipos
                    SET eliminado_en = NOW(),
                        version = version + 1
                    WHERE id = :id
                    AND eliminado_en IS NULL";

    $consultaEliminar = $pdo->prepare($sqlEliminar);
    $consultaEliminar->execute(['id' => $id]);

    if ($consultaEliminar->rowCount() === 0) {
        exit('No se pudo eliminar el equipo.');
    }

    unset($_SESSION['csrf_token']);

    header('Location: listar.php?eliminado=1');
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('Ocurrió un error al eliminar el equipo.');
}
