<?php

require_once __DIR__ . '/../../app/includes/auth.php';
requiereLogin();

// Verificar permiso
if (!tienePermiso('reporte_consultar')) {
    http_response_code(403);
    exit('No tienes permiso para consultar reportes.');
}

$pdo = require __DIR__ . '/../../app/config/config.php';

// Fechas recibidas
$fechaInicio = $_GET['fecha_inicio'] ?? '';
$fechaFin = $_GET['fecha_fin'] ?? '';

$resultados = [];
$error = '';

// Validar fechas
if ($fechaInicio !== '' || $fechaFin !== '') {

    $inicio = DateTime::createFromFormat('!Y-m-d', $fechaInicio);
    $fin = DateTime::createFromFormat('!Y-m-d', $fechaFin);

    $inicioValido = $inicio && $inicio->format('Y-m-d') === $fechaInicio;
    $finValido = $fin && $fin->format('Y-m-d') === $fechaFin;

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

// Función para mostrar texto seguro
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
    <title>Reporte de préstamos - SIGEL</title>
</head>

<body>

    <h1>Reporte de equipos más prestados</h1>

    <p>
        <a href="../dashboard.php">Volver al dashboard</a>
    </p>

    <form method="GET" action="listar.php">

        <label for="fecha_inicio">Fecha inicial:</label>

        <input
            type="date"
            id="fecha_inicio"
            name="fecha_inicio"
            value="<?= escapar($fechaInicio) ?>"
            required
        >

        <label for="fecha_fin">Fecha final:</label>

        <input
            type="date"
            id="fecha_fin"
            name="fecha_fin"
            value="<?= escapar($fechaFin) ?>"
            required
        >

        <button type="submit">Generar reporte</button>

    </form>

    <?php if ($error !== ''): ?>

        <p><?= escapar($error) ?></p>

    <?php elseif ($fechaInicio !== '' && $fechaFin !== ''): ?>

        <h2>Resultados</h2>

        <p>
            Periodo:
            <?= escapar($fechaInicio) ?>
            al
            <?= escapar($fechaFin) ?>
        </p>

        <?php if (empty($resultados)): ?>

            <p>No se encontraron préstamos en ese periodo.</p>

        <?php else: ?>

            <table border="1" cellpadding="8">

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

            <p>
                <a href="exportar.php?fecha_inicio=<?= urlencode($fechaInicio) ?>&fecha_fin=<?= urlencode($fechaFin) ?>">
                    Exportar a CSV
                </a>
            </p>

        <?php endif; ?>

    <?php endif; ?>

</body>
</html>
