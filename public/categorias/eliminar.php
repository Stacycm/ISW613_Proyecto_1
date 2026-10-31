<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

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

    // Iniciar una transacción
    $pdo->beginTransaction();

    // Buscar la categoría y bloquear el registro
    $sql = "SELECT id, nombre, dias_max_prestamo, eliminado_en
            FROM categoria
            WHERE id = :id
            AND eliminado_en IS NULL
            FOR UPDATE";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    $categoriaAnterior = $consulta->fetch();

    if (!$categoriaAnterior) {
        throw new RuntimeException('La categoría no existe o ya fue eliminada.');
    }

    // Comprobar si existen equipos asociados
    $sqlEquipos = "SELECT COUNT(*)
                   FROM equipos
                   WHERE categoria_id = :id
                   AND eliminado_en IS NULL";

    $consultaEquipos = $pdo->prepare($sqlEquipos);
    $consultaEquipos->execute(['id' => $id]);

    $totalEquipos = (int) $consultaEquipos->fetchColumn();

    if ($totalEquipos > 0) {
        throw new RuntimeException(
            'No puedes eliminar esta categoría porque tiene equipos asociados.'
        );
    }

    // Guardar los valores anteriores
    $valoresAntes = [
        'nombre' => $categoriaAnterior['nombre'],
        'dias_max_prestamo' => $categoriaAnterior['dias_max_prestamo'],
        'eliminado_en' => null
    ];

    // Eliminación lógica
    $sql = "UPDATE categoria
            SET eliminado_en = NOW()
            WHERE id = :id
            AND eliminado_en IS NULL";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    if ($consulta->rowCount() === 0) {
        throw new RuntimeException('No fue posible eliminar la categoría.');
    }

    // Guardar los valores nuevos
    $valoresDespues = [
        'nombre' => $categoriaAnterior['nombre'],
        'dias_max_prestamo' => $categoriaAnterior['dias_max_prestamo'],
        'eliminado_en' => date('Y-m-d H:i:s')
    ];

    // Registrar la acción en la bitácora
    registrarBitacora(
        $pdo,
        'ELIMINAR',
        'categoria',
        $id,
        $valoresAntes,
        $valoresDespues
    );

    // Confirmar los cambios
    $pdo->commit();

    // Eliminar el token utilizado
    unset($_SESSION['csrf_token']);

    // Redirigir al listado
    header('Location: listar.php?eliminado=1');
    exit;

} catch (Throwable $e) {

    // Deshacer los cambios si ocurre un error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    if ($e instanceof RuntimeException) {
        exit($e->getMessage());
    }

    http_response_code(500);
    exit('No fue posible eliminar la categoría.');
}
