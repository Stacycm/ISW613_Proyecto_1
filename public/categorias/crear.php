<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

requiereLogin();

// Verificar permiso para crear categorías
if (!tienePermiso('categoria_crear')) {
    http_response_code(403);
    exit('No tienes permiso para crear categorías.');
}

// Conexión a la base de datos
$pdo = require __DIR__ . '/../../app/config/config.php';

$error = '';

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validar token CSRF
    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }

    // Obtener los datos
    $nombre = trim($_POST['nombre'] ?? '');
    $dias = filter_var(
        $_POST['dias_max_prestamo'] ?? null,
        FILTER_VALIDATE_INT
    );

    // Validaciones
    if ($nombre === '') {
        $error = 'El nombre de la categoría es obligatorio.';

    } elseif (strlen($nombre) > 100) {
        $error = 'El nombre no puede superar los 100 caracteres.';

    } elseif ($dias === false || $dias === null || $dias <= 0) {
        $error = 'Los días máximos de préstamo deben ser un número mayor que cero.';

    } else {

        try {

            // Iniciar una transacción
            $pdo->beginTransaction();

            // Insertar la categoría
            $sql = "INSERT INTO categoria
                    (nombre, dias_max_prestamo)
                    VALUES
                    (:nombre, :dias)";

            $consulta = $pdo->prepare($sql);

            $consulta->execute([
                'nombre' => $nombre,
                'dias' => $dias
            ]);

            // Obtener el ID de la categoría creada
            $categoriaId = (int) $pdo->lastInsertId();

            // Registrar la acción en la bitácora
            registrarBitacora(
                $pdo,
                'CREAR',
                'categoria',
                $categoriaId,
                null,
                [
                    'nombre' => $nombre,
                    'dias_max_prestamo' => $dias
                ]
            );

            // Confirmar los cambios
            $pdo->commit();

            // Eliminar el token utilizado
            unset($_SESSION['csrf_token']);

            // Redirigir al listado
            header('Location: listar.php?creado=1');
            exit;

        } catch (Throwable $e) {

            // Deshacer los cambios si ocurre un error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            if ($e instanceof PDOException && $e->getCode() === '23000') {
                $error = 'Ya existe una categoría con ese nombre.';
            } else {
                http_response_code(500);
                $error = 'No fue posible registrar la categoría.';
            }
        }
    }
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
    <title>Crear categoría - SIGEL</title>
</head>

<body>

    <h1>Registrar categoría</h1>

    <a href="../dashboard.php">Volver al inicio</a>

    <br><br>

    <?php if ($error !== ''): ?>
        <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="POST" action="crear.php">

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
            value="<?= htmlspecialchars($_POST['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
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
            value="<?= htmlspecialchars($_POST['dias_max_prestamo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            required
        >

        <br><br>

        <button type="submit">Guardar categoría</button>

    </form>

</body>
</html>
