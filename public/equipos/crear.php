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

    // ==========================
    // Validar imagen
    // ==========================

    $imagenRuta = null;

    if (
        isset($_FILES['imagen']) &&
        $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
            $mensaje = 'No se pudo subir la imagen.';

        } elseif ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
            $mensaje = 'La imagen no puede superar los 5 MB.';

        } else {

            // Detectar el tipo MIME real del archivo
            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $mime = $finfo->file($_FILES['imagen']['tmp_name']);

            $tiposPermitidos = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            if (!isset($tiposPermitidos[$mime])) {
                $mensaje = 'Solo se permiten imágenes JPG, PNG o WEBP.';
            } else {

                // Crear nombre único
                $nombreArchivo = bin2hex(random_bytes(16))
                    . '.'
                    . $tiposPermitidos[$mime];

                // Carpeta donde se guardarán las imágenes
                $carpetaImagenes = __DIR__ . '/../uploads/equipos';

                if (!is_dir($carpetaImagenes)) {
                    if (!mkdir($carpetaImagenes, 0755, true)) {
                        $mensaje = 'No se pudo crear la carpeta de imágenes.';
                    }
                }

                if ($mensaje === '') {

                    $rutaCompleta = $carpetaImagenes . '/' . $nombreArchivo;

                    if (
                        !move_uploaded_file(
                            $_FILES['imagen']['tmp_name'],
                            $rutaCompleta
                        )
                    ) {
                        $mensaje = 'No se pudo guardar la imagen.';
                    } else {

                        // Ruta que guardaremos en la base de datos
                        $imagenRuta = 'uploads/equipos/' . $nombreArchivo;
                    }
                }
            }
        }
    }

    // ==========================
    // Validar datos del equipo
    // ==========================

    if ($mensaje === '') {

        if ($codigo === '' || $nombre === '') {
            $mensaje = 'Todos los campos son obligatorios.';

        } elseif (!$categoria_id || $categoria_id < 1) {
            $mensaje = 'Selecciona una categoría válida.';

        } elseif (!in_array($estado, $estadosPermitidos, true)) {
            $mensaje = 'Selecciona un estado válido.';
        }
    }

    // ==========================
    // Verificar categoría
    // ==========================

    if ($mensaje === '') {

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
        }
    }

    // ==========================
    // Guardar equipo
    // ==========================

    if ($mensaje === '') {

        try {

            $pdo->beginTransaction();

            // Insertar el equipo
            $sql = "INSERT INTO equipos
                    (
                        categoria_id,
                        codigo,
                        nombre,
                        estado,
                        imagen_ruta,
                        version
                    )
                    VALUES
                    (
                        :categoria_id,
                        :codigo,
                        :nombre,
                        :estado,
                        :imagen_ruta,
                        1
                    )";

            $consulta = $pdo->prepare($sql);

            $consulta->execute([
                'categoria_id' => $categoria_id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'estado' => $estado,
                'imagen_ruta' => $imagenRuta
            ]);

            // Obtener ID del equipo creado
            $equipoId = (int) $pdo->lastInsertId();

            // Registrar acción en bitácora
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
                    'estado' => $estado,
                    'imagen_ruta' => $imagenRuta
                ]
            );

            // Confirmar transacción
            $pdo->commit();

            // Renovar token CSRF
            unset($_SESSION['csrf_token']);

            header('Location: listar.php?creado=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Si la BD falló después de guardar la imagen,
            // eliminar la imagen para no dejar archivos huérfanos.
            if ($imagenRuta !== null) {

                $archivoGuardado = __DIR__ . '/../' . $imagenRuta;

                if (is_file($archivoGuardado)) {
                    unlink($archivoGuardado);
                }
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

?>

<h2>Registrar equipo</h2>

<?php if ($mensaje !== ''): ?>

    <p>
        <?= htmlspecialchars(
            $mensaje,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>
    </p>

<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            $_SESSION['csrf_token'],
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>"
    >

    <label>Código:</label>

    <input
        type="text"
        name="codigo"
        required
    >

    <br><br>

    <label>Nombre:</label>

    <input
        type="text"
        name="nombre"
        required
    >

    <br><br>

    <label>Categoría:</label>

    <select name="categoria_id" required>

        <option value="">
            Selecciona una categoría
        </option>

        <?php foreach ($categorias as $categoria): ?>

            <option value="<?= (int) $categoria['id'] ?>">

                <?= htmlspecialchars(
                    $categoria['nombre'],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>

            </option>

        <?php endforeach; ?>

    </select>

    <br><br>

    <label>Estado:</label>

    <select name="estado" required>

        <option value="Disponible">
            Disponible
        </option>

        <option value="Prestado">
            Prestado
        </option>

        <option value="Mantenimiento">
            Mantenimiento
        </option>

    </select>

    <br><br>

    <label>Imagen del equipo:</label>

    <input
        type="file"
        name="imagen"
        accept=".jpg,.jpeg,.png,.webp"
    >

    <br>

    <small>
        Formatos permitidos: JPG, PNG y WEBP. Máximo 5 MB.
    </small>

    <br><br>

    <button type="submit">
        Guardar equipo
    </button>

</form>

<br>

<a href="listar.php">
    Volver a la lista
</a>