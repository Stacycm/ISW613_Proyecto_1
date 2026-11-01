
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

// Consultar categorías
$sqlCategorias = "SELECT id, nombre
                  FROM categoria
                  WHERE eliminado_en IS NULL
                  ORDER BY nombre";

$categorias = $pdo->query($sqlCategorias)->fetchAll();

// Crear token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

    $imagenNueva = null;
    $rutaImagenAnterior = $equipo['imagen_ruta'];
    $rutaImagenNueva = $rutaImagenAnterior;
    $archivoSubido = null;

    // Validar los datos
    if ($codigo === '' || $nombre === '') {
        $mensaje = 'El código y el nombre son obligatorios.';

    } elseif (!$categoria_id || $categoria_id < 1) {
        $mensaje = 'Selecciona una categoría válida.';

    } elseif (!in_array($estado, $estadosPermitidos, true)) {
        $mensaje = 'Selecciona un estado válido.';
    }

    // Validar categoría
    if ($mensaje === '') {

        $sqlCategoria = "SELECT COUNT(*)
                         FROM categoria
                         WHERE id = :id
                         AND eliminado_en IS NULL";

        $consultaCategoria = $pdo->prepare($sqlCategoria);
        $consultaCategoria->execute(['id' => $categoria_id]);

        if ((int) $consultaCategoria->fetchColumn() === 0) {
            $mensaje = 'La categoría seleccionada no existe.';
        }
    }

    // Validar imagen, si se seleccionó una
    if (
        $mensaje === '' &&
        isset($_FILES['imagen']) &&
        $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
            $mensaje = 'Ocurrió un error al subir la imagen.';

        } elseif ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
            $mensaje = 'La imagen no puede superar los 5 MB.';

        } else {

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $tipo = $finfo->file($_FILES['imagen']['tmp_name']);

            $tiposPermitidos = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($tiposPermitidos[$tipo])) {
                $mensaje = 'Solo se permiten imágenes JPG, PNG o WEBP.';

            } elseif (getimagesize($_FILES['imagen']['tmp_name']) === false) {
                $mensaje = 'El archivo seleccionado no es una imagen válida.';

            } else {

                $carpeta = __DIR__ . '/../uploads/equipos/';

                if (!is_dir($carpeta)) {
                    mkdir($carpeta, 0755, true);
                }

                $nombreArchivo = bin2hex(random_bytes(16))
                    . '.' . $tiposPermitidos[$tipo];

                $rutaDestino = $carpeta . $nombreArchivo;

                if (
                    move_uploaded_file(
                        $_FILES['imagen']['tmp_name'],
                        $rutaDestino
                    )
                ) {
                    $imagenNueva = $rutaDestino;
                    $archivoSubido = $rutaDestino;
                    $rutaImagenNueva = 'uploads/equipos/' . $nombreArchivo;

                } else {
                    $mensaje = 'No se pudo guardar la imagen.';
                }
            }
        }
    }

    // Actualizar equipo
    if ($mensaje === '') {

        try {

            $pdo->beginTransaction();

            // Bloquear equipo
            $sqlAnterior = "SELECT *
                            FROM equipos
                            WHERE id = :id
                            AND eliminado_en IS NULL
                            FOR UPDATE";

            $consultaAnterior = $pdo->prepare($sqlAnterior);
            $consultaAnterior->execute(['id' => $id]);

            $equipoAnterior = $consultaAnterior->fetch();

            if (!$equipoAnterior) {
                throw new RuntimeException('El equipo ya no existe.');
            }

            $valoresAntes = [
                'codigo' => $equipoAnterior['codigo'],
                'nombre' => $equipoAnterior['nombre'],
                'categoria_id' => (int) $equipoAnterior['categoria_id'],
                'estado' => $equipoAnterior['estado'],
                'imagen_ruta' => $equipoAnterior['imagen_ruta']
            ];

            $sql = "UPDATE equipos
                    SET codigo = :codigo,
                        nombre = :nombre,
                        categoria_id = :categoria_id,
                        estado = :estado,
                        imagen_ruta = :imagen_ruta,
                        version = version + 1
                    WHERE id = :id
                    AND eliminado_en IS NULL";

            $consulta = $pdo->prepare($sql);

            $consulta->execute([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'categoria_id' => $categoria_id,
                'estado' => $estado,
                'imagen_ruta' => $rutaImagenNueva,
                'id' => $id
            ]);

            $valoresDespues = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'categoria_id' => (int) $categoria_id,
                'estado' => $estado,
                'imagen_ruta' => $rutaImagenNueva
            ];

            registrarBitacora(
                $pdo,
                'EDITAR',
                'equipos',
                (int) $id,
                $valoresAntes,
                $valoresDespues
            );

            $pdo->commit();

            // Eliminar imagen anterior después del commit
            if (
                $imagenNueva !== null &&
                !empty($rutaImagenAnterior)
            ) {
                $rutaAnteriorCompleta = __DIR__ . '/../' . $rutaImagenAnterior;

                if (is_file($rutaAnteriorCompleta)) {
                    unlink($rutaAnteriorCompleta);
                }
            }

            unset($_SESSION['csrf_token']);

            header('Location: listar.php?actualizado=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Eliminar archivo nuevo si falló la actualización
            if (
                $archivoSubido !== null &&
                is_file($archivoSubido)
            ) {
                unlink($archivoSubido);
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

?>

<h2>Editar equipo</h2>

<?php if ($mensaje !== ''): ?>
    <p>
        <?= htmlspecialchars($mensaje, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </p>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

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
        <?php foreach ($estadosPermitidos as $estado): ?>
            <option
                value="<?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>"
                <?= ($_POST['estado'] ?? $equipo['estado']) === $estado ? 'selected' : '' ?>
            >
                <?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    <label>Imagen actual:</label>
    <br>

    <?php if (!empty($equipo['imagen_ruta'])): ?>
        <img
            src="/<?= htmlspecialchars($equipo['imagen_ruta'], ENT_QUOTES, 'UTF-8') ?>"
            alt="Imagen del equipo"
            width="150"
        >
    <?php else: ?>
        <p>Este equipo no tiene imagen.</p>
    <?php endif; ?>

    <br><br>

    <label>Seleccionar una imagen nueva (opcional):</label>
    <input
        type="file"
        name="imagen"
        accept=".jpg,.jpeg,.png,.webp"
    >

    <br><br>

    <button type="submit">Guardar cambios</button>

</form>

<br>

<a href="listar.php">Volver a la lista</a>
