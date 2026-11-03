<?php

require_once __DIR__ . '/../../app/includes/auth.php';

requiereLogin();

// Verificar permiso
if (!tienePermiso('bitacora_consultar')) {
    http_response_code(403);
    exit('No tienes permiso para consultar la bitácora.');
}

// Conexión
$pdo = require __DIR__ . '/../../app/config/config.php';

// Paginación
$porPagina = 10;

$pagina = filter_var(
    $_GET['pagina'] ?? 1,
    FILTER_VALIDATE_INT
);

if (!$pagina || $pagina < 1) {
    $pagina = 1;
}

$buscar = trim($_GET['buscar'] ?? '');
$accion = trim($_GET['accion'] ?? '');

// Consultas y filtros
$where = [];
$parametros = [];

if ($buscar !== '') {
    $where[] = "(
        u.nombre LIKE :buscar
        OR b.entidad LIKE :buscar
        OR CAST(b.entidad_id AS CHAR) LIKE :buscar
    )";

    $parametros['buscar'] = "%$buscar%";
}

if ($accion !== '') {
    $where[] = "b.accion = :accion";
    $parametros['accion'] = $accion;
}

$condicion = '';

if (!empty($where)) {
    $condicion = ' WHERE ' . implode(' AND ', $where);
}

// Contar registros
$sqlTotal = "SELECT COUNT(*)
             FROM bitacora b
             LEFT JOIN usuarios u ON u.id = b.usuario_id
             $condicion";

$consultaTotal = $pdo->prepare($sqlTotal);
$consultaTotal->execute($parametros);

$totalRegistros = (int) $consultaTotal->fetchColumn();

$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));

if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}

$inicio = ($pagina - 1) * $porPagina;

// Obtener registros
$sql = "SELECT
            b.id,
            b.usuario_id,
            u.nombre AS usuario,
            b.accion,
            b.entidad,
            b.entidad_id,
            b.valores_antes,
            b.valores_despues,
            b.ip,
            b.creado_en
        FROM bitacora b
        LEFT JOIN usuarios u ON u.id = b.usuario_id
        $condicion
        ORDER BY b.creado_en DESC, b.id DESC
        LIMIT :limite OFFSET :inicio";

$consulta = $pdo->prepare($sql);

foreach ($parametros as $nombre => $valor) {
    $consulta->bindValue(':' . $nombre, $valor);
}

$consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$consulta->bindValue(':inicio', $inicio, PDO::PARAM_INT);

$consulta->execute();

$registros = $consulta->fetchAll();

// Función para proteger la salida HTML
function escapar($valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Función para mostrar JSON de forma legible
function mostrarJson(?string $json): string
{
    if ($json === null || $json === '') {
        return 'Sin información';
    }

    $datos = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $json;
    }

    return json_encode(
        $datos,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Bitácora - SIGEL</title>

    <link rel="stylesheet" href="/css/estilos.css">
</head>

<body>

<main class="contenedor contenedor-modulo">

    <header class="titulo-modulo">
        <div>
            <h1>Bitácora del sistema</h1>
            <p>
                Consulta las acciones realizadas por los usuarios
                dentro de SIGEL.
            </p>
        </div>

        <a href="/dashboard.php" class="boton boton-secundario">
            Volver al dashboard
        </a>
    </header>

    <section class="panel-modulo">

        <div class="panel-titulo">
            <h2>Filtros de búsqueda</h2>
        </div>

        <form method="GET" class="filtros-bitacora">

            <input
                type="text"
                name="buscar"
                placeholder="Buscar usuario, entidad o ID"
                value="<?= escapar($buscar) ?>"
            >

            <select name="accion">

                <option value="">Todas las acciones</option>

                <?php
                $acciones = [
                    'CREAR',
                    'EDITAR',
                    'DESACTIVAR',
                    'ELIMINAR',
                    'APROBAR',
                    'CANCELAR',
                    'ENTREGAR',
                    'DEVOLVER'
                ];

                foreach ($acciones as $opcion):
                ?>

                    <option
                        value="<?= escapar($opcion) ?>"
                        <?= $accion === $opcion ? 'selected' : '' ?>
                    >
                        <?= escapar($opcion) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" class="boton boton-principal">
                Buscar
            </button>

            <a href="listar.php" class="boton boton-secundario">
                Limpiar filtros
            </a>

        </form>

    </section>

    <section class="panel-modulo">

        <div class="panel-titulo">
            <h2>Registros de actividad</h2>
        </div>

        <p class="contador-resultados">
            Total de registros:
            <strong><?= $totalRegistros ?></strong>
        </p>

        <div class="tabla-contenedor tabla-bitacora">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Entidad</th>
                        <th>ID afectado</th>
                        <th>Fecha</th>
                        <th>IP</th>
                        <th>Valores anteriores</th>
                        <th>Valores nuevos</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($registros)): ?>

                        <tr>
                            <td colspan="9" class="sin-registros">
                                No se encontraron registros.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($registros as $registro): ?>

                            <tr>

                                <td><?= escapar($registro['id']) ?></td>

                                <td>
                                    <?= escapar(
                                        $registro['usuario'] ?? 'Usuario no disponible'
                                    ) ?>
                                </td>

                                <td>
                                    <span class="etiqueta-accion">
                                        <?= escapar($registro['accion']) ?>
                                    </span>
                                </td>

                                <td><?= escapar($registro['entidad']) ?></td>

                                <td><?= escapar($registro['entidad_id']) ?></td>

                                <td><?= escapar($registro['creado_en']) ?></td>

                                <td><?= escapar($registro['ip']) ?></td>

                                <td>
                                    <pre><?= escapar(
                                        mostrarJson($registro['valores_antes'])
                                    ) ?></pre>
                                </td>

                                <td>
                                    <pre><?= escapar(
                                        mostrarJson($registro['valores_despues'])
                                    ) ?></pre>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="paginacion">

            <?php if ($pagina > 1): ?>

                <a
                    class="boton boton-secundario"
                    href="?pagina=<?= $pagina - 1 ?>&buscar=<?= urlencode($buscar) ?>&accion=<?= urlencode($accion) ?>"
                >
                    ← Anterior
                </a>

            <?php endif; ?>

            <span>
                Página <?= $pagina ?> de <?= $totalPaginas ?>
            </span>

            <?php if ($pagina < $totalPaginas): ?>

                <a
                    class="boton boton-secundario"
                    href="?pagina=<?= $pagina + 1 ?>&buscar=<?= urlencode($buscar) ?>&accion=<?= urlencode($accion) ?>"
                >
                    Siguiente →
                </a>

            <?php endif; ?>

        </div>

    </section>

</main>

</body>
</html>
