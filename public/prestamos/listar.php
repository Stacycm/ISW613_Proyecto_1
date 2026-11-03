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

// Función para mostrar el estado con un estilo
function claseEstado(string $estado): string
{
    $clases = [
        'solicitado' => 'estado-solicitado',
        'aprobado' => 'estado-aprobado',
        'entregado' => 'estado-entregado',
        'devuelto' => 'estado-devuelto',
        'cancelado' => 'estado-cancelado'
    ];

    return $clases[$estado] ?? '';
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Préstamos - SIGEL</title>

    <link rel="stylesheet" href="/css/estilos.css">
</head>

<body>

<main class="contenedor contenedor-modulo">

    <header class="titulo-modulo">
        <div>
            <h1>Gestión de préstamos</h1>
            <p>Consulta y administra las solicitudes de préstamo de equipos.</p>
        </div>

        <a href="../dashboard.php" class="boton boton-secundario">
            Volver al dashboard
        </a>
    </header>

    <?php if ($puedeGestionar): ?>
        <div class="acciones-modulo">
            <a href="crear.php" class="boton boton-principal">
                + Registrar nueva solicitud
            </a>
        </div>
    <?php endif; ?>

    <!-- Mensajes de confirmación -->

    <?php if (isset($_GET['creado'])): ?>
        <div class="mensaje mensaje-exito">
            La solicitud fue registrada correctamente.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['aprobado'])): ?>
        <div class="mensaje mensaje-exito">
            El préstamo fue aprobado correctamente.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['entregado'])): ?>
        <div class="mensaje mensaje-exito">
            El préstamo fue entregado correctamente.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['devuelto'])): ?>
        <div class="mensaje mensaje-exito">
            La devolución fue registrada correctamente.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['cancelado'])): ?>
        <div class="mensaje mensaje-exito">
            El préstamo fue cancelado correctamente.
        </div>
    <?php endif; ?>

    <!-- Búsqueda y filtros -->

    <section class="panel-modulo">

        <div class="panel-titulo">
            <h2>Buscar préstamos</h2>
        </div>

        <form method="GET" action="listar.php" class="formulario-busqueda">

            <div class="campo-formulario">
                <label for="busqueda">Buscar por usuario o equipo:</label>

                <input
                    type="text"
                    name="busqueda"
                    id="busqueda"
                    value="<?= escapar($busqueda) ?>"
                    placeholder="Nombre, correo o código del equipo"
                >
            </div>

            <div class="campo-formulario">
                <label for="estado">Filtrar por estado:</label>

                <select name="estado" id="estado">

                    <option value="">Todos los estados</option>

                    <?php foreach ($estadosPermitidos as $opcion): ?>

                        <option
                            value="<?= escapar($opcion) ?>"
                            <?= $estado === $opcion ? 'selected' : '' ?>
                        >
                            <?= escapar(ucfirst($opcion)) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div class="acciones-busqueda">

                <button type="submit" class="boton boton-principal">
                    Buscar
                </button>

                <a href="listar.php" class="boton boton-secundario">
                    Limpiar filtros
                </a>

            </div>

        </form>

    </section>

    <!-- Resultados -->

    <section class="panel-modulo">

        <div class="panel-titulo">
            <h2>Solicitudes registradas</h2>

            <span class="contador-resultados">
                <?= $totalPrestamos ?> préstamo(s) encontrado(s)
            </span>
        </div>

        <?php if (empty($prestamos)): ?>

            <div class="sin-resultados">
                <p>No se encontraron préstamos.</p>
                <span>Prueba cambiando los filtros de búsqueda.</span>
            </div>

        <?php else: ?>

            <div class="lista-prestamos">

                <?php foreach ($prestamos as $prestamo): ?>

                    <article class="tarjeta-prestamo">

                        <div class="encabezado-prestamo">

                            <div>
                                <span class="numero-prestamo">
                                    Préstamo #<?= (int) $prestamo['id'] ?>
                                </span>

                                <h3>
                                    <?= escapar($prestamo['usuario_nombre']) ?>
                                </h3>
                            </div>

                            <span class="estado-prestamo <?= escapar(claseEstado($prestamo['estado'])) ?>">
                                <?= escapar(ucfirst($prestamo['estado'])) ?>
                            </span>

                        </div>

                        <div class="datos-prestamo">

                            <div>
                                <strong>Correo</strong>
                                <span><?= escapar($prestamo['usuario_correo']) ?></span>
                            </div>

                            <div>
                                <strong>Fecha de inicio</strong>
                                <span><?= escapar($prestamo['fecha_inicio']) ?></span>
                            </div>

                            <div>
                                <strong>Fecha de finalización</strong>
                                <span><?= escapar($prestamo['fecha_fin']) ?></span>
                            </div>

                            <div>
                                <strong>Operador</strong>
                                <span>
                                    <?= escapar($prestamo['operador_nombre'] ?? 'Sin asignar') ?>
                                </span>
                            </div>

                        </div>

                        <div class="equipos-prestamo">

                            <h4>Equipos solicitados</h4>

                            <?php if (!empty($detalles[$prestamo['id']])): ?>

                                <div class="lista-equipos-prestamo">

                                    <?php foreach ($detalles[$prestamo['id']] as $detalle): ?>

                                        <div class="equipo-prestamo">

                                            <div class="informacion-equipo-prestamo">

                                                <strong>
                                                    <?= escapar($detalle['equipo_nombre']) ?>
                                                </strong>

                                                <span>
                                                    Código: <?= escapar($detalle['equipo_codigo']) ?>
                                                </span>

                                            </div>

                                            <div class="acciones-equipo-prestamo">

                                                <?php if ($detalle['fecha_devolucion'] !== null): ?>

                                                    <span class="etiqueta-devuelto">
                                                        Devuelto el:
                                                        <?= escapar($detalle['fecha_devolucion']) ?>
                                                    </span>

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

                                                        <button
                                                            type="submit"
                                                            class="boton boton-pequeno boton-devolver"
                                                        >
                                                            Registrar devolución
                                                        </button>

                                                    </form>

                                                <?php else: ?>

                                                    <span class="texto-pendiente">
                                                        Pendiente de devolución
                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php else: ?>

                                <p>Este préstamo no tiene equipos registrados.</p>

                            <?php endif; ?>

                        </div>

                        <!-- Acciones del préstamo -->

                        <?php if (
                            $prestamo['estado'] === 'solicitado'
                            && $puedeGestionar
                        ): ?>

                            <div class="acciones-prestamo">

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

                                    <button
                                        type="submit"
                                        class="boton boton-pequeno boton-aprobar"
                                    >
                                        Aprobar solicitud
                                    </button>

                                </form>

                            </div>

                        <?php endif; ?>

                        <?php if (
                            $prestamo['estado'] === 'aprobado'
                            && $puedeGestionar
                        ): ?>

                            <div class="acciones-prestamo">

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

                                    <button
                                        type="submit"
                                        class="boton boton-pequeno boton-entregar"
                                    >
                                        Registrar entrega
                                    </button>

                                </form>

                            </div>

                        <?php endif; ?>

                        <?php if (
                            in_array($prestamo['estado'], ['solicitado', 'aprobado'], true)
                            && $puedeGestionar
                        ): ?>

                            <div class="acciones-prestamo">

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

                                    <button
                                        type="submit"
                                        class="boton boton-pequeno boton-cancelar"
                                    >
                                        Cancelar préstamo
                                    </button>

                                </form>

                            </div>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

    <!-- Paginación -->

    <?php if ($totalPrestamos > 0): ?>

        <nav class="paginacion">

            <span>
                Página <?= $pagina ?> de <?= $totalPaginas ?>
            </span>

            <div class="paginacion-botones">

                <?php if ($pagina > 1): ?>

                    <a
                        class="boton boton-secundario"
                        href="?pagina=<?= $pagina - 1 ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado) ?>"
                    >
                        Anterior
                    </a>

                <?php endif; ?>

                <?php if ($pagina < $totalPaginas): ?>

                    <a
                        class="boton boton-secundario"
                        href="?pagina=<?= $pagina + 1 ?>&busqueda=<?= urlencode($busqueda) ?>&estado=<?= urlencode($estado) ?>"
                    >
                        Siguiente
                    </a>

                <?php endif; ?>

            </div>

        </nav>

    <?php endif; ?>

</main>

</body>
</html>
