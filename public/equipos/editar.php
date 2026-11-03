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

// Estados permitidos
$estadosPermitidos = [
    'Disponible',
    'Prestado',
    'Mantenimiento'
];

// Crear token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }

    $codigo = trim($_POST['codigo'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');

    $categoria_id = filter_var(
        $_POST['categoria_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $estado = $_POST['estado'] ?? '';

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

                // Carpeta pública de imágenes
                $carpeta = dirname(__DIR__) . '/uploads/equipos/';

                if (!is_dir($carpeta)) {
                    if (!mkdir($carpeta, 0755, true)) {
                        $mensaje = 'No se pudo crear la carpeta de imágenes.';
                    }
                }

                if ($mensaje === '') {

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

                        // Mantener el mismo formato de ruta que crear.php
                        $rutaImagenNueva = '/uploads/equipos/' . $nombreArchivo;

                    } else {
                        $mensaje = 'No se pudo guardar la imagen.';
                    }
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

            // Registrar cambios en bitácora
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
                $rutaAnteriorCompleta = dirname(__DIR__) . '/'
                    . ltrim($rutaImagenAnterior, '/');

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

// Valores que se mostrarán en el formulario
$codigoFormulario = $_POST['codigo'] ?? $equipo['codigo'];
$nombreFormulario = $_POST['nombre'] ?? $equipo['nombre'];
$categoriaFormulario = $_POST['categoria_id'] ?? $equipo['categoria_id'];
$estadoFormulario = $_POST['estado'] ?? $equipo['estado'];

$rutaImagenMostrar = !empty($equipo['imagen_ruta'])
    ? '/' . ltrim($equipo['imagen_ruta'], '/')
    : null;

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar equipo - SIGEL</title>
    <link rel="stylesheet" href="/css/estilos.css">
</head>

<body>

    <main class="contenedor contenedor-modulo">

        <header class="titulo-modulo">
            <div>
                <h1>Editar equipo</h1>
                <p>Modifica la información del equipo seleccionado.</p>
            </div>

            <a href="listar.php" class="boton boton-secundario">
                Volver a equipos
            </a>
        </header>

        <section class="panel-modulo formulario-panel">

            <div class="panel-titulo">
                <h2>Información del equipo</h2>
            </div>

            <?php if ($mensaje !== ''): ?>
                <div class="mensaje mensaje-error">
                    <?= htmlspecialchars(
                        $mensaje,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <form
                method="POST"
                action="editar.php?id=<?= (int) $id ?>"
                enctype="multipart/form-data"
                class="formulario-modulo"
            >

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int) $equipo['id'] ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>"
                >

                <div class="campo-formulario">
                    <label for="codigo">Código del equipo:</label>

                    <input
                        type="text"
                        id="codigo"
                        name="codigo"
                        value="<?= htmlspecialchars(
                            $codigoFormulario,
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </div>

                <div class="campo-formulario">
                    <label for="nombre">Nombre del equipo:</label>

                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        value="<?= htmlspecialchars(
                            $nombreFormulario,
                            ENT_QUOTES | ENT_SUBSTITUTE,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </div>

                <div class="campo-formulario">
                    <label for="categoria_id">Categoría:</label>

                    <select id="categoria_id" name="categoria_id" required>

                        <?php foreach ($categorias as $categoria): ?>

                            <option
                                value="<?= (int) $categoria['id'] ?>"
                                <?= (int) $categoriaFormulario === (int) $categoria['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $categoria['nombre'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="campo-formulario">
                    <label for="estado">Estado del equipo:</label>

                    <select id="estado" name="estado" required>

                        <?php foreach ($estadosPermitidos as $estadoOpcion): ?>

                            <option
                                value="<?= htmlspecialchars($estadoOpcion, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $estadoFormulario === $estadoOpcion ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($estadoOpcion, ENT_QUOTES, 'UTF-8') ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="campo-formulario">
                    <label>Imagen actual:</label>

                    <?php if ($rutaImagenMostrar !== null): ?>

                        <div class="imagen-equipo-actual">
                            <img
                                src="<?= htmlspecialchars(
                                    $rutaImagenMostrar,
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>"
                                alt="Imagen actual del equipo"
                            >
                        </div>

                    <?php else: ?>

                        <p class="texto-secundario">
                            Este equipo todavía no tiene una imagen.
                        </p>

                    <?php endif; ?>
                </div>

                <div class="campo-formulario">
                    <label for="imagen">Seleccionar una imagen nueva:</label>

                    <input
                        type="file"
                        id="imagen"
                        name="imagen"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <small>
                        Este campo es opcional. Si no seleccionas una imagen,
                        se conservará la actual. Formatos JPG, PNG y WEBP,
                        máximo 5 MB.
                    </small>
                </div>

                <div class="acciones-formulario">

                    <a href="listar.php" class="boton boton-secundario">
                        Cancelar
                    </a>

                    <button type="submit" class="boton boton-principal">
                        Guardar cambios
                    </button>

                </div>

            </form>

        </section>

    </main>

</body>
</html>
