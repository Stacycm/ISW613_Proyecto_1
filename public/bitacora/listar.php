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

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 25px;
            color: #333;
        }

        .contenedor {
            max-width: 1200px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
        }

        h1 {
            color: #24476b;
        }

        .filtros {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        input, select, button {
            padding: 9px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            background: #24476b;
            color: white;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #24476b;
            color: white;
        }

        tr:nth-child(even) {
            background: #f7f9fc;
        }

        pre {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            max-width: 250px;
            font-size: 12px;
        }

        .paginacion {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .paginacion a, .volver {
            color: #24476b;
            text-decoration: none;
            font-weight: bold;
        }

        .volver {
            display: inline-block;
            margin-bottom: 15px;
        }

        .sin-registros {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>

<body>

<div class="contenedor">

    <a class="volver" href="/dashboard.php">
        ← Volver al dashboard
    </a>

    <h1>Bitácora del sistema</h1>

    <p>
        Aquí puedes consultar las acciones realizadas por los usuarios
        dentro de SIGEL.
    </p>

    <!-- Filtros -->
    <form method="GET" class="filtros">

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

        <button type="submit">Buscar</button>

        <a href="listar.php">Limpiar filtros</a>

    </form>

    <p>
        Total de registros: <strong><?= $totalRegistros ?></strong>
    </p>

    <!-- Tabla -->
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

                        <td><?= escapar($registro['accion']) ?></td>

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

    <!-- Paginación -->
    <div class="paginacion">

        <?php if ($pagina > 1): ?>

            <a href="?pagina=<?= $pagina - 1 ?>&buscar=<?= urlencode($buscar) ?>&accion=<?= urlencode($accion) ?>">
                ← Anterior
            </a>

        <?php endif; ?>

        <span>
            Página <?= $pagina ?> de <?= $totalPaginas ?>
        </span>

        <?php if ($pagina < $totalPaginas): ?>

            <a href="?pagina=<?= $pagina + 1 ?>&buscar=<?= urlencode($buscar) ?>&accion=<?= urlencode($accion) ?>">
                Siguiente →
            </a>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
