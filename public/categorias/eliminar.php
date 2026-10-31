<?php

require_once __DIR__ . '/../../app/includes/auth.php';

requiereLogin();

// Verificar permiso
if (!tienePermiso('categoria_eliminar')) {
    http_response_code(403);
    exit('No tienes permiso para eliminar categorías.');
}

// Aceptar solamente solicitudes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

// Validar token CSRF
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Solicitud no válida.');
}

// Obtener ID
$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    exit('Categoría no válida.');
}

try {

    $pdo = require __DIR__ . '/../../app/config/config.php';

    // Comprobar si existen equipos asociados
    $sqlEquipos = "SELECT COUNT(*)
                   FROM equipos
                   WHERE categoria_id = :id
                   AND eliminado_en IS NULL";

    $consultaEquipos = $pdo->prepare($sqlEquipos);
    $consultaEquipos->execute(['id' => $id]);

    $totalEquipos = (int) $consultaEquipos->fetchColumn();

    if ($totalEquipos > 0) {
        exit('No puedes eliminar esta categoría porque tiene equipos asociados.');
    }

    // Eliminación lógica
    $sql = "UPDATE categoria
            SET eliminado_en = NOW()
            WHERE id = :id
            AND eliminado_en IS NULL";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    if ($consulta->rowCount() === 0) {
        exit('La categoría no existe o ya fue eliminada.');
    }

    unset($_SESSION['csrf_token']);

    header('Location: listar.php?eliminado=1');
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('No fue posible eliminar la categoría.');
}
