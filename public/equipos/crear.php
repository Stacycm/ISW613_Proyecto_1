<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

requiereLogin();

if (!tienePermiso('equipo_crear')) {
    http_response_code(403);
    exit('No tienes permiso para registrar equipos.');
}

$pdo = require __DIR__ . '/../../app/config/config.php';

$mensaje = '';

// Consultar las categorías disponibles
$sql = "SELECT id, nombre
        FROM categoria
        WHERE eliminado_en IS NULL
        ORDER BY nombre";

$consulta = $pdo->query($sql);
$categorias = $consulta->fetchAll();

// Crear token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        exit('Solicitud no válida.');
    }

    $codigo = trim($_POST['codigo'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');

    $categoria_id = filter_var(
        $_POST['categoria_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $estado = $_POST['estado'] ?? '';

    $estadosPermitidos = [
        'Disponible',
        'Prestado',
        'Mantenimiento'
    ];

    if ($codigo === '' || $nombre === '') {
        $mensaje = 'Todos los campos son obligatorios.';

    } elseif (!$categoria_id || $categoria_id < 1) {
        $mensaje = 'Selecciona una categoría válida.';

    } elseif (!in_array($estado, $estadosPermitidos, true)) {
        $mensaje = 'Selecciona un estado válido.';

    } else {

        // Verificar que la categoría exista y esté activa
        $sqlCategoria = "SELECT COUNT(*)
                         FROM categoria
                         WHERE id = :id
                         AND eliminado_en IS NULL";

        $consultaCategoria = $pdo->prepare($sqlCategoria);
        $consultaCategoria->execute([
            'id' => $categoria_id
        ]);

        if ((int) $consultaCategoria->fetchColumn() === 0) {
            $mensaje = 'La categoría seleccionada no existe.';

        } else {

            try {

                // Iniciar transacción
                $pdo->beginTransaction();

                // Insertar el equipo
                $sql = "INSERT INTO equipos
                        (categoria_id, codigo, nombre, estado, version)
                        VALUES
                        (:categoria_id, :codigo, :nombre, :estado, 1)";

                $consulta = $pdo->prepare($sql);

                $consulta->execute([
                    'categoria_id' => $categoria_id,
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'estado' => $estado
                ]);

                // Obtener el ID del equipo creado
                $equipoId = (int) $pdo->lastInsertId();

                // Registrar la acción en la bitácora
                registrarBitacora(
                    $pdo,
                    'CREAR',
                    'equipos',
                    $equipoId,
                    null,
                    [
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                        'categoria_id' => $categoria_id,
                        'estado' => $estado
                    ]
                );

                // Confirmar la transacción
                $pdo->commit();

                // Renovar token CSRF
                unset($_SESSION['csrf_token']);

                header('Location: listar.php?creado=1');
                exit;

            } catch (Throwable $e) {

                // Si ocurre un error, deshacer los cambios
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (
                    $e instanceof PDOException &&
                    $e->getCode() === '23000'
                ) {
                    $mensaje = 'Ese código de equipo ya está registrado.';
                } else {
                    throw $e;
                }
            }
        }
    }
}
?>

<h2>Registrar equipo</h2>

<?php if ($mensaje !== ''): ?>
    <p><?= htmlspecialchars($mensaje, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<?php endif; ?>

<form method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
    >

    <label>Código:</label>
    <input type="text" name="codigo" required>

    <br><br>

    <label>Nombre:</label>
    <input type="text" name="nombre" required>

    <br><br>

    <label>Categoría:</label>
    <select name="categoria_id" required>
        <option value="">Selecciona una categoría</option>

        <?php foreach ($categorias as $categoria): ?>
            <option value="<?= (int) $categoria['id'] ?>">
                <?= htmlspecialchars($categoria['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>

    </select>

    <br><br>

    <label>Estado:</label>
    <select name="estado" required>
        <option value="Disponible">Disponible</option>
        <option value="Prestado">Prestado</option>
        <option value="Mantenimiento">Mantenimiento</option>
    </select>

    <br><br>

    <button type="submit">Guardar equipo</button>

</form>

<br>

<a href="listar.php">Volver a la lista</a>
