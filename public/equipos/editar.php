<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

requiereLogin();

if (!tienePermiso('equipo_editar')) {
    http_response_code(403);
    exit('No tienes permiso para editar equipos.');
}

$pdo = require __DIR__ . '/../../app/config/config.php';

$mensaje = '';

// Obtener el ID del equipo
$id = filter_var(
    $_POST['id'] ?? $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$id || $id < 1) {
    exit('ID de equipo no válido.');
}

// Consultar el equipo
$sql = "SELECT *
        FROM equipos
        WHERE id = :id
        AND eliminado_en IS NULL";

$consulta = $pdo->prepare($sql);
$consulta->execute(['id' => $id]);

$equipo = $consulta->fetch();

if (!$equipo) {
    exit('El equipo no existe.');
}

// Consultar categorías disponibles
$sqlCategorias = "SELECT id, nombre
                  FROM categoria
                  WHERE eliminado_en IS NULL
                  ORDER BY nombre";

$consultaCategorias = $pdo->query($sqlCategorias);
$categorias = $consultaCategorias->fetchAll();

// Crear token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesar formulario
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
        $mensaje = 'El código y el nombre son obligatorios.';

    } elseif (!$categoria_id || $categoria_id < 1) {
        $mensaje = 'Selecciona una categoría válida.';

    } elseif (!in_array($estado, $estadosPermitidos, true)) {
        $mensaje = 'Selecciona un estado válido.';

    } else {

        // Verificar que la categoría esté activa
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

                // Consultar y bloquear el equipo
                $sqlAnterior = "SELECT codigo, nombre, categoria_id, estado
                                FROM equipos
                                WHERE id = :id
                                AND eliminado_en IS NULL
                                FOR UPDATE";

                $consultaAnterior = $pdo->prepare($sqlAnterior);
                $consultaAnterior->execute([
                    'id' => $id
                ]);

                $equipoAnterior = $consultaAnterior->fetch();

                if (!$equipoAnterior) {
                    throw new RuntimeException('El equipo ya no existe.');
                }

                // Guardar los valores anteriores
                $valoresAntes = [
                    'codigo' => $equipoAnterior['codigo'],
                    'nombre' => $equipoAnterior['nombre'],
                    'categoria_id' => (int) $equipoAnterior['categoria_id'],
                    'estado' => $equipoAnterior['estado']
                ];

                // Actualizar el equipo
                $sql = "UPDATE equipos
                        SET codigo = :codigo,
                            nombre = :nombre,
                            categoria_id = :categoria_id,
                            estado = :estado,
                            version = version + 1
                        WHERE id = :id
                        AND eliminado_en IS NULL";

                $consulta = $pdo->prepare($sql);

                $consulta->execute([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'categoria_id' => $categoria_id,
                    'estado' => $estado,
                    'id' => $id
                ]);

                // Guardar los valores nuevos
                $valoresDespues = [
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'categoria_id' => (int) $categoria_id,
                    'estado' => $estado
                ];

                // Registrar la modificación en la bitácora
                registrarBitacora(
                    $pdo,
                    'EDITAR',
                    'equipos',
                    (int) $id,
                    $valoresAntes,
                    $valoresDespues
                );

                // Confirmar la transacción
                $pdo->commit();

                // Renovar token CSRF
                unset($_SESSION['csrf_token']);

                header('Location: listar.php?actualizado=1');
                exit;

            } catch (Throwable $e) {

                // Deshacer los cambios si ocurre un error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if (
                    $e instanceof PDOException &&
                    $e->getCode() === '23000'
                ) {
                    $mensaje = 'Ese código ya está registrado.';

                } elseif ($e instanceof RuntimeException) {
                    $mensaje = $e->getMessage();

                } else {
                    throw $e;
                }
            }
        }
    }
}

?>

<h2>Editar equipo</h2>

<?php if ($mensaje !== ''): ?>
    <p>
        <?= htmlspecialchars($mensaje, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </p>
<?php endif; ?>

<form method="POST">

    <input type="hidden" name="id" value="<?= (int) $equipo['id'] ?>">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
    >

    <label>Código:</label>

    <input
        type="text"
        name="codigo"
        value="<?= htmlspecialchars($_POST['codigo'] ?? $equipo['codigo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        required
    >

    <br><br>

    <label>Nombre:</label>

    <input
        type="text"
        name="nombre"
        value="<?= htmlspecialchars($_POST['nombre'] ?? $equipo['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        required
    >

    <br><br>

    <label>Categoría:</label>

    <select name="categoria_id" required>

        <?php foreach ($categorias as $categoria): ?>

            <option
                value="<?= (int) $categoria['id'] ?>"
                <?= (int) ($_POST['categoria_id'] ?? $equipo['categoria_id']) === (int) $categoria['id'] ? 'selected' : '' ?>
            >
                <?= htmlspecialchars($categoria['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </option>

        <?php endforeach; ?>

    </select>

    <br><br>

    <label>Estado:</label>

    <select name="estado" required>

        <?php foreach (['Disponible', 'Prestado', 'Mantenimiento'] as $estado): ?>

            <option
                value="<?= $estado ?>"
                <?= ($_POST['estado'] ?? $equipo['estado']) === $estado ? 'selected' : '' ?>
            >
                <?= $estado ?>
            </option>

        <?php endforeach; ?>

    </select>

    <br><br>

    <button type="submit">Guardar cambios</button>

</form>

<br>

<a href="listar.php">Volver a la lista</a>
