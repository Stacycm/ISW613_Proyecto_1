<?php

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/bitacora.php';

requiereLogin();

// Verificar permiso
if (!tienePermiso('prestamo_gestionar_solicitudes')) {
    http_response_code(403);
    exit('No tienes permiso para registrar solicitudes de préstamo.');
}

$pdo = require __DIR__ . '/../../app/config/config.php';

$mensaje = '';

// Consultar usuarios activos
$sqlUsuarios = "SELECT id, nombre, correo
                FROM usuarios
                WHERE activo = 1
                ORDER BY nombre";

$consultaUsuarios = $pdo->query($sqlUsuarios);
$usuarios = $consultaUsuarios->fetchAll();

// Consultar equipos disponibles
$sqlEquipos = "SELECT id, codigo, nombre
               FROM equipos
               WHERE estado = 'Disponible'
               AND eliminado_en IS NULL
               ORDER BY nombre";

$consultaEquipos = $pdo->query($sqlEquipos);
$equipos = $consultaEquipos->fetchAll();

// Crear token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validar CSRF
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Solicitud no válida.');
    }

    // Recibir datos
    $usuario_id = filter_var(
        $_POST['usuario_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';

    $equipoIds = $_POST['equipos'] ?? [];

    // Validar que los equipos sean un arreglo
    if (!is_array($equipoIds)) {
        $equipoIds = [];
    }

    // Validar que cada ID sea un entero positivo
    $equipoIdsValidos = true;

    foreach ($equipoIds as $equipoId) {
        if (
            !is_scalar($equipoId) ||
            filter_var($equipoId, FILTER_VALIDATE_INT) === false ||
            (int) $equipoId < 1
        ) {
            $equipoIdsValidos = false;
            break;
        }
    }

    if ($equipoIdsValidos) {
        $equipoIds = array_map('intval', $equipoIds);
        $equipoIds = array_unique($equipoIds);
        $equipoIds = array_values($equipoIds);
    } else {
        $equipoIds = [];
    }

    // Validar fechas
    $inicio = DateTime::createFromFormat('!Y-m-d', $fecha_inicio);
    $fin = DateTime::createFromFormat('!Y-m-d', $fecha_fin);

    $fechasValidas =
        $inicio &&
        $fin &&
        $inicio->format('Y-m-d') === $fecha_inicio &&
        $fin->format('Y-m-d') === $fecha_fin;

    if (!$usuario_id || $usuario_id < 1) {

        $mensaje = 'Selecciona un usuario válido.';

    } elseif (!$fechasValidas || $fecha_fin <= $fecha_inicio) {

        $mensaje = 'Las fechas seleccionadas no son válidas.';

    } elseif (!$equipoIdsValidos || count($equipoIds) === 0) {

        $mensaje = 'Debes seleccionar equipos válidos.';

    } else {

        try {

            // Iniciar transacción
            $pdo->beginTransaction();

            // Comprobar que el usuario exista y esté activo
            $sqlUsuario = "SELECT id
                           FROM usuarios
                           WHERE id = :id
                           AND activo = 1";

            $consultaUsuario = $pdo->prepare($sqlUsuario);
            $consultaUsuario->execute(['id' => $usuario_id]);

            if (!$consultaUsuario->fetch()) {
                throw new RuntimeException(
                    'El usuario seleccionado no existe o está inactivo.'
                );
            }

            // Ordenar los equipos para bloquearlos en el mismo orden
            sort($equipoIds);

            // Crear marcadores para la consulta
            $marcadores = implode(
                ',',
                array_fill(0, count($equipoIds), '?')
            );

            // Bloquear los equipos y consultar sus días máximos
            $sqlBloqueo = "SELECT
                               e.id,
                               e.estado,
                               c.dias_max_prestamo
                           FROM equipos e
                           INNER JOIN categoria c ON c.id = e.categoria_id
                           WHERE e.id IN ($marcadores)
                           AND e.eliminado_en IS NULL
                           ORDER BY e.id
                           FOR UPDATE";

            $consultaBloqueo = $pdo->prepare($sqlBloqueo);
            $consultaBloqueo->execute($equipoIds);

            $equiposSeleccionados = $consultaBloqueo->fetchAll();

            if (count($equiposSeleccionados) !== count($equipoIds)) {
                throw new RuntimeException(
                    'Uno o varios equipos no existen o fueron eliminados.'
                );
            }

            // Calcular los días solicitados
            $diasSolicitados = (int) $inicio->diff($fin)->days;

            // Comprobar disponibilidad y duración máxima
            foreach ($equiposSeleccionados as $equipo) {

                if ($equipo['estado'] !== 'Disponible') {
                    throw new RuntimeException(
                        'El equipo con ID ' . $equipo['id'] .
                        ' no está disponible.'
                    );
                }

                $diasMaximos = (int) $equipo['dias_max_prestamo'];

                if ($diasMaximos < 1) {
                    throw new RuntimeException(
                        'La categoría del equipo con ID ' . $equipo['id'] .
                        ' no tiene configurado un máximo de días válido.'
                    );
                }

                if ($diasSolicitados > $diasMaximos) {
                    throw new RuntimeException(
                        'El equipo con ID ' . $equipo['id'] .
                        ' permite un máximo de ' . $diasMaximos .
                        ' días de préstamo. Estás solicitando ' .
                        $diasSolicitados . ' días.'
                    );
                }
            }

            // Verificar que no existan préstamos con fechas traslapadas
            $sqlConflicto = "SELECT COUNT(*)
                             FROM prestamo_detalle pd
                             INNER JOIN prestamos p
                                ON p.id = pd.prestamo_id
                             WHERE pd.equipo_id = :equipo_id
                             AND p.estado IN (
                                'solicitado',
                                'aprobado',
                                'entregado'
                             )
                             AND p.fecha_inicio < :fecha_fin
                             AND p.fecha_fin > :fecha_inicio";

            $consultaConflicto = $pdo->prepare($sqlConflicto);

            foreach ($equipoIds as $equipoId) {

                $consultaConflicto->execute([
                    'equipo_id' => $equipoId,
                    'fecha_inicio' => $fecha_inicio,
                    'fecha_fin' => $fecha_fin
                ]);

                if ((int) $consultaConflicto->fetchColumn() > 0) {
                    throw new RuntimeException(
                        'Uno de los equipos ya tiene un préstamo en esas fechas.'
                    );
                }
            }

            // Registrar quién gestiona la solicitud.
            // Si es Administrador, operador_id queda NULL.
            $operador_id = (int) $_SESSION['rol_id'] === 2
                ? (int) $_SESSION['id']
                : null;

            // Crear el préstamo
            $sqlPrestamo = "INSERT INTO prestamos
                            (
                                usuario_id,
                                operador_id,
                                estado,
                                fecha_inicio,
                                fecha_fin
                            )
                            VALUES
                            (
                                :usuario_id,
                                :operador_id,
                                'solicitado',
                                :fecha_inicio,
                                :fecha_fin
                            )";

            $consultaPrestamo = $pdo->prepare($sqlPrestamo);

            $consultaPrestamo->execute([
                'usuario_id' => $usuario_id,
                'operador_id' => $operador_id,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin
            ]);

            // Obtener el ID del préstamo recién creado
            $prestamo_id = (int) $pdo->lastInsertId();

            // Registrar los equipos del préstamo
            $sqlDetalle = "INSERT INTO prestamo_detalle
                           (prestamo_id, equipo_id)
                           VALUES (:prestamo_id, :equipo_id)";

            $consultaDetalle = $pdo->prepare($sqlDetalle);

            foreach ($equipoIds as $equipoId) {

                $consultaDetalle->execute([
                    'prestamo_id' => $prestamo_id,
                    'equipo_id' => $equipoId
                ]);
            }

            // Guardar los valores nuevos para la bitácora
            $valoresDespues = [
                'usuario_id' => (int) $usuario_id,
                'operador_id' => $operador_id,
                'estado' => 'solicitado',
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'dias_solicitados' => $diasSolicitados,
                'equipos' => $equipoIds
            ];

            // Registrar la creación en la bitácora
            registrarBitacora(
                $pdo,
                'CREAR',
                'prestamos',
                $prestamo_id,
                null,
                $valoresDespues
            );

            // Confirmar todas las operaciones
            $pdo->commit();

            unset($_SESSION['csrf_token']);

            header('Location: listar.php?creado=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof PDOException) {
                error_log($e->getMessage());
                $mensaje = 'Ocurrió un error al registrar el préstamo. Revisa los datos e inténtalo nuevamente.';
            } else {
                $mensaje = $e->getMessage();
            }
        }
    }
}

?>

<h2>Registrar solicitud de préstamo</h2>

<?php if ($mensaje !== ''): ?>

    <p>
        <?= htmlspecialchars(
            $mensaje,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>
    </p>

<?php endif; ?>

<form method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars(
            $_SESSION['csrf_token'],
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>"
    >

    <label>Usuario solicitante:</label>

    <select name="usuario_id" required>

        <option value="">Selecciona un usuario</option>

        <?php foreach ($usuarios as $usuario): ?>

            <option
                value="<?= (int) $usuario['id'] ?>"
                <?= (string) ($_POST['usuario_id'] ?? '') === (string) $usuario['id'] ? 'selected' : '' ?>
            >
                <?= htmlspecialchars(
                    $usuario['nombre'] . ' - ' . $usuario['correo'],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <br><br>

    <label>Fecha de inicio:</label>

    <input
        type="date"
        name="fecha_inicio"
        value="<?= htmlspecialchars(
            $_POST['fecha_inicio'] ?? '',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>"
        required
    >

    <br><br>

    <label>Fecha de finalización:</label>

    <input
        type="date"
        name="fecha_fin"
        value="<?= htmlspecialchars(
            $_POST['fecha_fin'] ?? '',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>"
        required
    >

    <br><br>

    <label>Selecciona los equipos:</label>

    <br>

    <?php if (count($equipos) > 0): ?>

        <?php foreach ($equipos as $equipo): ?>

            <label>

                <input
                    type="checkbox"
                    name="equipos[]"
                    value="<?= (int) $equipo['id'] ?>"
                    <?= in_array(
                        (int) $equipo['id'],
                        array_map(
                            'intval',
                            is_array($_POST['equipos'] ?? null)
                                ? $_POST['equipos']
                                : []
                        ),
                        true
                    ) ? 'checked' : '' ?>
                >

                <?= htmlspecialchars(
                    $equipo['codigo'] . ' - ' . $equipo['nombre'],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>

            </label>

            <br>

        <?php endforeach; ?>

    <?php else: ?>

        <p>No hay equipos disponibles.</p>

    <?php endif; ?>

    <br>

    <button type="submit">
        Registrar solicitud
    </button>

</form>

<br>

<a href="../dashboard.php">Volver al inicio</a>
