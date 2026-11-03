<?php

require_once __DIR__ . '/../../app/includes/auth.php';

requiereLogin();

// Verificar permiso para consultar reportes
if (!tienePermiso('reporte_consultar')) {
    http_response_code(403);
    exit('No tienes permiso para consultar reportes.');
}

// Conexión a la base de datos
$pdo = require __DIR__ . '/../../app/config/config.php';

// Recibir fechas
$fechaInicio = $_GET['fecha_inicio'] ?? '';
$fechaFin = $_GET['fecha_fin'] ?? '';

$resultados = [];
$error = '';

// Función para mostrar texto seguro
function escapar($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Validar fechas
if ($fechaInicio !== '' || $fechaFin !== '') {

    $inicio = DateTime::createFromFormat('!Y-m-d', $fechaInicio);
    $fin = DateTime::createFromFormat('!Y-m-d', $fechaFin);

    $inicioValido = $inicio
        && $inicio->format('Y-m-d') === $fechaInicio;

    $finValido = $fin
        && $fin->format('Y-m-d') === $fechaFin;

    if (!$inicioValido || !$finValido) {

        $error = 'Debes seleccionar fechas válidas.';

    } elseif ($fechaInicio > $fechaFin) {

        $error = 'La fecha inicial no puede ser posterior a la fecha final.';

    } else {

        // Consultar equipos más prestados
        $sql = "SELECT
                    e.id,
                    e.codigo,
                    e.nombre,
                    COUNT(*) AS total_prestamos
                FROM prestamo_detalle pd
                INNER JOIN prestamos p
                    ON p.id = pd.prestamo_id
                INNER JOIN equipos e
                    ON e.id = pd.equipo_id
                WHERE p.fecha_inicio >= :fecha_inicio
                AND p.fecha_inicio <= :fecha_fin
                AND p.estado IN ('aprobado', 'entregado', 'devuelto')
                GROUP BY e.id, e.codigo, e.nombre
                ORDER BY total_prestamos DESC, e.nombre ASC";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ]);

        $resultados = $consulta->fetchAll();
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reporte de préstamos - SIGEL</title>

    <link rel="stylesheet" href="/css/estilos.css">
</head>

<body>

<main class="contenedor contenedor-modulo">

    <header class="titulo-modulo">
        <div>
            <h1>Reporte de equipos más prestados</h1>
            <p>Consulta cuáles equipos han tenido más préstamos durante un periodo.</p>
        </div>

        <a href="../dashboard.php" class="boton boton-secundario">
            Volver al dashboard
        </a>
    </header>

    <section class="panel-modulo">

        <div class="panel-titulo">
            <h2>Filtrar reporte</h2>
        </div>

        <form method="GET" action="listar.php" class="formulario-modulo">

            <div class="campo-formulario">
                <label for="fecha_inicio">Fecha inicial:</label>

                <input
                    type="date"
                    id="fecha_inicio"
                    name="fecha_inicio"
                    value="<?= escapar($fechaInicio) ?>"
                    required
                >
            </div>

            <div class="campo-formulario">
                <label for="fecha_fin">Fecha final:</label>

                <input
                    type="date"
                    id="fecha_fin"
                    name="fecha_fin"
                    value="<?= escapar($fechaFin) ?>"
                    required
                >
            </div>

            <div class="acciones-formulario">
                <button type="submit" class="boton boton-principal">
                    Generar reporte
                </button>
            </div>

        </form>

    </section>

    <?php if ($error !== ''): ?>

        <div class="mensaje mensaje-error">
            <?= escapar($error) ?>
        </div>

    <?php elseif ($fechaInicio !== '' && $fechaFin !== ''): ?>

        <section class="panel-modulo resultados-reporte">

            <div class="panel-titulo">
                <h2>Resultados</h2>
            </div>

            <p class="periodo-reporte">
                **Periodo consultado:**
                <?= escapar($fechaInicio) ?>
                al
                <?= escapar($fechaFin) ?>
            </p>

            <?php if (empty($resultados)): ?>

                <p class="mensaje">
                    No se encontraron equipos prestados en ese periodo.
                </p>

            <?php else: ?>

                <div class="tabla-contenedor">

                    <table>
                        <thead>
                            <tr>
                                <th>Posición</th>
                                <th>Código</th>
                                <th>Equipo</th>
                                <th>Total de préstamos</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($resultados as $indice => $equipo): ?>

                                <tr>
                                    <td><?= $indice + 1 ?></td>
                                    <td><?= escapar($equipo['codigo']) ?></td>
                                    <td><?= escapar($equipo['nombre']) ?></td>
                                    <td><?= (int) $equipo['total_prestamos'] ?></td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>

                <div class="acciones-reporte">
                    <a
                        href="exportar.php?fecha_inicio=<?= urlencode($fechaInicio) ?>&fecha_fin=<?= urlencode($fechaFin) ?>"
                        class="boton boton-secundario"
                    >
                        Exportar a CSV
                    </a>
                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</main>

</body>
</html>
