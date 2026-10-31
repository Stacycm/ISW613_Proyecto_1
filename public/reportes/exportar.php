<?php

require_once __DIR__ . '/../../app/includes/auth.php';
requiereLogin();

// Verificar permiso
if (!tienePermiso('reporte_exportar')) {
    http_response_code(403);
    exit('No tienes permiso para exportar reportes.');
}

// Conexión
$pdo = require __DIR__ . '/../../app/config/config.php';

// Recibir fechas
$fechaInicio = $_GET['fecha_inicio'] ?? '';
$fechaFin = $_GET['fecha_fin'] ?? '';

// Validar fechas
$inicio = DateTime::createFromFormat('!Y-m-d', $fechaInicio);
$fin = DateTime::createFromFormat('!Y-m-d', $fechaFin);

$inicioValido = $inicio && $inicio->format('Y-m-d') === $fechaInicio;
$finValido = $fin && $fin->format('Y-m-d') === $fechaFin;

if (!$inicioValido || !$finValido || $fechaInicio > $fechaFin) {
    http_response_code(400);
    exit('El rango de fechas no es válido.');
}

// Consultar equipos más prestados
$sql = "SELECT
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

// Nombre del archivo
$nombreArchivo = 'reporte_equipos_' . $fechaInicio . '_' . $fechaFin . '.csv';

// Encabezados de descarga
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Abrir salida
$salida = fopen('php://output', 'w');

// BOM para compatibilidad con Excel y UTF-8
fwrite($salida, "\xEF\xBB\xBF");

// Título del reporte
fputcsv($salida, [
    'REPORTE DE EQUIPOS MAS PRESTADOS'
], ';');

fputcsv($salida, [
    'Fecha inicial: ' . $fechaInicio
], ';');

fputcsv($salida, [
    'Fecha final: ' . $fechaFin
], ';');

// Espacio
fputcsv($salida, [], ';');

// Encabezados de columnas
fputcsv($salida, [
    'Codigo del equipo',
    'Nombre del equipo',
    'Total de prestamos'
], ';');

// Datos
foreach ($resultados as $equipo) {

    fputcsv($salida, [
        $equipo['codigo'],
        $equipo['nombre'],
        (int) $equipo['total_prestamos']
    ], ';');

}

fclose($salida);
exit;

