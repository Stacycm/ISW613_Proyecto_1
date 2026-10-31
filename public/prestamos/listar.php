<?php

require_once __DIR__ . '/../../app/includes/auth.php';
requiereLogin();

// Verificar permiso de consulta
if (!tienePermiso('prestamo_consultar')) {
    http_response_code(403);
    exit('No tienes permiso para consultar préstamos.');
}

$pdo = require __DIR__ . '/../../app/config/config.php';

// Permisos para mostrar los botones
$puedeGestionar = tienePermiso('prestamo_gestionar_solicitudes');
$puedeDevolver = tienePermiso('prestamo_devoluciones');

// Crear token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_token'];

// Búsqueda
$busqueda = trim($_GET['busqueda'] ?? '');

// Filtro de estado
$estadosPermitidos = [
    'solicitado',
    'aprobado',
    'entregado',
    'devuelto',
    'cancelado'
];

$estado = $_GET['estado'] ?? '';

if (!in_array($estado, $estadosPermitidos, true)) {
    $estado = '';
}

// Paginación
$porPagina = 10;

$pagina = filter_var(
    $_GET['pagina'] ?? 1,
    FILTER_VALIDATE_INT
);

if (!$pagina || $pagina < 1) {
    $pagina = 1;
}

// Construir condiciones
$condiciones = [];
$parametros = [];

if ($busqueda !== '') {

    $condiciones[] = "(
        u.nombre LIKE :busqueda_nombre
        OR u.correo LIKE :busqueda_correo
        OR EXISTS (
            SELECT 1
            FROM prestamo_detalle pd_busqueda
            INNER JOIN equipos e_busqueda
                ON e_busqueda.id = pd_busqueda.equipo_id
            WHERE pd_busqueda.prestamo_id = p.id
            AND (
                e_busqueda.nombre LIKE :busqueda_equipo
                OR e_busqueda.codigo LIKE :busqueda_codigo
            )
        )
    )";

    $valorBusqueda = "%$busqueda%";

    $parametros['busqueda_nombre'] = $valorBusqueda;
    $parametros['busqueda_correo'] = $valorBusqueda;
    $parametros['busqueda_equipo'] = $valorBusqueda;
    $parametros['busqueda_codigo'] = $valorBusqueda;
}

if ($estado !== '') {
    $condiciones[] = "p.estado = :estado";
    $parametros['estado'] = $estado;
}

$where = '';

if (!empty($condiciones)) {
    $where = 'WHERE ' . implode(' AND ', $condiciones);
}

// Contar préstamos
$sql = "SELECT COUNT(*)
        FROM prestamos p
        INNER JOIN usuarios u ON u.id = p.usuario_id
        $where";

$consulta = $pdo->prepare($sql);
$consulta->execute($parametros);

$totalPrestamos = (int) $consulta->fetchColumn();

$totalPaginas = max(1, (int) ceil($totalPrestamos / $porPagina));

if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}

$inicio = ($pagina - 1) * $porPagina;

// Obtener préstamos
$sql = "SELECT
            p.id,
            p.estado,
            p.fecha_inicio,
            p.fecha_fin,
            u.nombre AS usuario_nombre,
            u.correo AS usuario_correo,
            operador.nombre AS operador_nombre
        FROM prestamos p
        INNER JOIN usuarios u ON u.id = p.usuario_id
        LEFT JOIN usuarios operador ON operador.id = p.operador_id
        $where
        ORDER BY p.id DESC
        LIMIT :limite OFFSET :inicio";

$consulta = $pdo->prepare($sql);

foreach ($parametros as $clave => $valor) {
    $consulta->bindValue(':' . $clave, $valor);
}

$consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$consulta->bindValue(':inicio', $inicio, PDO::PARAM_INT);

$consulta->execute();

$prestamos = $consulta->fetchAll();

// Obtener los equipos de los préstamos encontrados
$detalles = [];

if (!empty($prestamos)) {

    $ids = array_column($prestamos, 'id');

    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $sql = "SELECT
                pd.prestamo_id,
                pd.equipo_id,
                pd.fecha_devolucion,
                e.nombre AS equipo_nombre,
                e.codigo AS equipo_codigo
            FROM prestamo_detalle pd
            INNER JOIN equipos e ON e.id = pd.equipo_id
            WHERE pd.prestamo_id IN ($marcadores)
            ORDER BY pd.prestamo_id, e.nombre";

    $consulta = $pdo->prepare($sql);
    $consulta->execute($ids);

    foreach ($consulta->fetchAll() as $detalle) {
        $detalles[$detalle['prestamo_id']][] = $detalle;
    }
}

// Función para mostrar texto de forma segura
function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préstamos - SIGEL</title>
</head>

<body>

    <h1>Gestión de préstamos</h1>

    <p>
        <a href="../dashboard.php">Volver al dashboard</a>
    </p>

    <?php if ($puedeGestionar): ?>
        <p>
            <a href="crear.php">Registrar nueva solicitud</a>
        </p>
    <?php endif; ?>

    <?php if (isset($_GET['creado'])): ?>
        <p>La solicitud fue registrada correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['aprobado'])): ?>
        <p>El préstamo fue aprobado correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['entregado'])): ?>
        <p>El préstamo fue entregado correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['devuelto'])): ?>
        <p>La devolución fue registrada correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['cancelado'])): ?>
        <p>El préstamo fue cancelado correctamente.</p>
    <?php endif; ?>

    <hr>

    <!-- Búsqueda y filtro -->
    <form method="GET" action="listar.php">

        <label for="busqueda">Buscar:</label>

        <input
            type="text"
            name="busqueda"
            id="busqueda"
            value="<?= escapar($busqueda) ?>"
            placeholder="Usuario, correo o equipo"
        >

        <label for="estado">Estado:</label>

        <select name="estado" id="estado">
            <option value="">Todos</option>

            <?php foreach ($estadosPermitidos as $opcion): ?>
                <option
                    value="<?= escapar($opcion) ?>"
                    <?= $estado === $opcion ? 'selected' : '' ?>
                >
                    <?= ucfirst($opcion) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <button type="submit">Buscar</button>

        <a href="listar.php">Limpiar</a>

    </form>

    <hr>

    <p>
        Total de préstamos encontrados: <?= $totalPrestamos ?>
    </p>

    <?php if (empty($prestamos)): ?>

        <p>No se encontraron préstamos.</p>

    <?php else: ?>

        <?php foreach ($prestamos as $prestamo): ?>

            <section>

                <h2>Préstamo #<?= (int) $prestamo['id'] ?></h2>

                <p>
                    <strong>Usuario:</strong>
                    <?= escapar($prestamo['usuario_nombre']) ?>
                </p>

                <p>
                    <strong>Correo:</strong>
                    <?= escapar($prestamo['usuario_correo']) ?>
                </p>

                <p>
                    <strong>Fecha de inicio:</strong>
                    <?= escapar($prestamo['fecha_inicio']) ?>
                </p>

                <p>
                    <strong>Fecha de finalización:</strong>
                    <?= escapar($prestamo['fecha_fin']) ?>
                </p>

                <p>
                    <strong>Operador:</strong>
                    <?= escapar($prestamo['operador_nombre'] ?? 'Sin asignar') ?>
                </p>

                <p>
                    <strong>Estado:</strong>
                    <?= escapar(ucfirst($prestamo['estado'])) ?>
                </p>

                <h3>Equipos</h3>

                <?php if (!empty($detalles[$prestamo['id']])): ?>

                    <ul>

                        <?php foreach ($detalles[$prestamo['id']] as $detalle): ?>

                            <li>

                                <?= escapar($detalle['equipo_nombre']) ?>
                                (<?= escapar($detalle['equipo_codigo']) ?>)

                                <?php if ($detalle['fecha_devolucion'] !== null): ?>

                                    <p>
                                        Devuelto el:
                                        <?= escapar($detalle['fecha_devolucion']) ?>
                                    </p>

                                <?php elseif (
                                    $prestamo['estado'] === 'entregado'
                                    && $puedeDevolver
                                ): ?>

                                    <form method="POST" action="devolver.php">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= escapar($csrf) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="prestamo_id"
                                            value="<?= (int) $prestamo['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="equipo_id"
                                            value="<?= (int) $detalle['equipo_id'] ?>"
                                        >

                                        <button type="submit">
                                            Registrar devolución
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <p>Pendiente de devolución.</p>

                                <?php endif; ?>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                <?php else: ?>

                    <p>Este préstamo no tiene equipos registrados.</p>

                <?php endif; ?>

                <!-- Acciones del préstamo -->

                <?php if (
                    $prestamo['estado'] === 'solicitado'
                    && $puedeGestionar
                ): ?>

                    <form method="POST" action="aprobar.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escapar($csrf) ?>"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $prestamo['id'] ?>"
                        >

                        <button type="submit">Aprobar solicitud</button>

                    </form>

                <?php endif; ?>

                <?php if (
                    $prestamo['estado'] === 'aprobado'
                    && $puedeGestionar
                ): ?>

                    <form method="POST" action="entregar.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escapar($csrf) ?>"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $prestamo['id'] ?>"
                        >

                        <button type="submit">Registrar entrega</button>

                    </form>

                <?php endif; ?>

                <?php if (
                    in_array($prestamo['estado'], ['solicitado', 'aprobado'], true)
                    && $puedeGestionar
                ): ?>

                    <form method="POST" action="cancelar.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escapar($csrf) ?>"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $prestamo['id'] ?>"
                        >

                        <button type="submit">Cancelar préstamo</button>

                    </form>

                <?php endif; ?>

                <hr>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

    <!-- Paginación -->

    <?php if ($totalPrestamos > 0): ?>

        <nav>

            <?php if ($pagina > 1): ?>

                <a href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado) ?>">
                    Anterior
                </a>

            <?php endif; ?>

            Página <?= $pagina ?> de <?= $totalPaginas ?>

            <?php if ($pagina < $totalPaginas): ?>

                <a href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado) ?>">
                    Siguiente
                </a>

            <?php endif; ?>

        </nav>

    <?php endif; ?>

</body>
</html>