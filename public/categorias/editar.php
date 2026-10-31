<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

requiereLogin();

// Verificar permiso
if (!tienePermiso('categoria_editar')) {
    http_response_code(403);
    exit('No tienes permiso para editar categorías.');
}

$pdo = require __DIR__ . '/../../app/config/config.php';

$error = '';

// Obtener el ID
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    exit('Categoría no válida.');
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validar CSRF
    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }

    $nombre = trim($_POST['nombre'] ?? '');

    $dias = filter_var(
        $_POST['dias_max_prestamo'] ?? null,
        FILTER_VALIDATE_INT
    );

    // Validaciones
    if ($nombre === '') {
        $error = 'El nombre es obligatorio.';

    } elseif (strlen($nombre) > 100) {
        $error = 'El nombre no puede superar los 100 caracteres.';

    } elseif ($dias === false || $dias === null || $dias <= 0) {
        $error = 'Los días máximos deben ser mayores que cero.';

    } else {

        try {

            // Iniciar una transacción
            $pdo->beginTransaction();

            // Buscar la categoría y bloquear el registro
            $sql = "SELECT id, nombre, dias_max_prestamo
                    FROM categoria
                    WHERE id = :id
                    AND eliminado_en IS NULL
                    FOR UPDATE";

            $consulta = $pdo->prepare($sql);
            $consulta->execute(['id' => $id]);

            $categoriaAnterior = $consulta->fetch();

            if (!$categoriaAnterior) {
                throw new RuntimeException('La categoría no existe.');
            }

            // Guardar los valores anteriores
            $valoresAntes = [
                'nombre' => $categoriaAnterior['nombre'],
                'dias_max_prestamo' => $categoriaAnterior['dias_max_prestamo']
            ];

            // Actualizar la categoría
            $sql = "UPDATE categoria
                    SET nombre = :nombre,
                        dias_max_prestamo = :dias
                    WHERE id = :id
                    AND eliminado_en IS NULL";

            $consulta = $pdo->prepare($sql);

            $consulta->execute([
                'nombre' => $nombre,
                'dias' => $dias,
                'id' => $id
            ]);

            // Guardar los valores nuevos
            $valoresDespues = [
                'nombre' => $nombre,
                'dias_max_prestamo' => $dias
            ];

            // Registrar la modificación en la bitácora
            registrarBitacora(
                $pdo,
                'EDITAR',
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
            header('Location: listar.php?actualizado=1');
            exit;

        } catch (Throwable $e) {

            // Deshacer los cambios si ocurre un error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            if ($e instanceof PDOException && $e->getCode() === '23000') {
                $error = 'Ya existe una categoría con ese nombre.';
            } elseif ($e instanceof RuntimeException) {
                $error = $e->getMessage();
            } else {
                http_response_code(500);
                $error = 'No fue posible actualizar la categoría.';
            }
        }
    }

} else {

    // Buscar la categoría que se desea editar
    $sql = "SELECT id, nombre, dias_max_prestamo
            FROM categoria
            WHERE id = :id
            AND eliminado_en IS NULL";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['id' => $id]);

    $categoria = $consulta->fetch();

    if (!$categoria) {
        exit('La categoría no existe.');
    }
}

// Recuperar datos para mostrar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $dias = $_POST['dias_max_prestamo'] ?? '';
} else {
    $nombre = $categoria['nombre'];
    $dias = $categoria['dias_max_prestamo'];
}

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Editar categoría - SIGEL</title>
</head>

<body>

    <h1>Editar categoría</h1>

    <a href="listar.php">Volver al listado</a>

    <br><br>

    <?php if ($error !== ''): ?>
        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="POST" action="editar.php">

        <input
            type="hidden"
            name="id"
            value="<?= $id ?>"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <label for="nombre">Nombre de la categoría:</label>

        <input
            type="text"
            id="nombre"
            name="nombre"
            maxlength="100"
            value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <br><br>

        <label for="dias_max_prestamo">
            Días máximos de préstamo:
        </label>

        <input
            type="number"
            id="dias_max_prestamo"
            name="dias_max_prestamo"
            min="1"
            value="<?= htmlspecialchars((string) $dias, ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <br><br>

        <button type="submit">Guardar cambios</button>

    </form>

</body>
</html>

