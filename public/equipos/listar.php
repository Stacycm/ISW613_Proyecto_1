<?php

require_once __DIR__ . '/../../app/includes/auth.php';

requiereLogin();

if (!tienePermiso('equipo_consultar')) {
    http_response_code(403);
    exit('No tienes permiso para consultar equipos.');
}

$pdo = require __DIR__ . '/../../app/config/config.php';

// Verificar permisos
$puedeCrear = tienePermiso('equipo_crear');
$puedeEditar = tienePermiso('equipo_editar');
$puedeEliminar = tienePermiso('equipo_eliminar');

// Crear token CSRF para eliminar
if ($puedeEliminar && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Búsqueda
$buscar = trim($_GET['buscar'] ?? '');

// Paginación
$porPagina = 5;

$pagina = filter_var(
    $_GET['pagina'] ?? 1,
    FILTER_VALIDATE_INT
);

if (!$pagina || $pagina < 1) {
    $pagina = 1;
}

// Consultar cantidad de equipos
$sqlTotal = "SELECT COUNT(*)
             FROM equipos e
             INNER JOIN categoria c ON c.id = e.categoria_id
             WHERE e.eliminado_en IS NULL
             AND c.eliminado_en IS NULL
             AND (
                 e.nombre LIKE :nombre
                 OR e.codigo LIKE :codigo
             )";

$consultaTotal = $pdo->prepare($sqlTotal);

$consultaTotal->execute([
    'nombre' => "%$buscar%",
    'codigo' => "%$buscar%"
]);

$total = (int) $consultaTotal->fetchColumn();

$totalPaginas = (int) ceil($total / $porPagina);

// Evitar páginas fuera del rango
if ($totalPaginas > 0 && $pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}

$inicio = ($pagina - 1) * $porPagina;

// Consultar equipos
$sql = "SELECT
            e.id,
            e.codigo,
            e.nombre,
            e.estado,
            c.nombre AS categoria
        FROM equipos e
        INNER JOIN categoria c ON c.id = e.categoria_id
        WHERE e.eliminado_en IS NULL
        AND c.eliminado_en IS NULL
        AND (
            e.nombre LIKE :nombre
            OR e.codigo LIKE :codigo
        )
        ORDER BY e.id DESC
        LIMIT :inicio, :cantidad";

$consulta = $pdo->prepare($sql);

$consulta->bindValue(':nombre', "%$buscar%", PDO::PARAM_STR);
$consulta->bindValue(':codigo', "%$buscar%", PDO::PARAM_STR);
$consulta->bindValue(':inicio', $inicio, PDO::PARAM_INT);
$consulta->bindValue(':cantidad', $porPagina, PDO::PARAM_INT);

$consulta->execute();

$equipos = $consulta->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de equipos - SIGEL</title>
    <link rel="stylesheet" href="/css/estilos.css">
</head>

<body>

    <main class="contenedor contenedor-modulo">

        <header class="titulo-modulo">
            <div>
                <h1>Gestión de equipos</h1>
                <p>Consulta y administra los equipos registrados en SIGEL.</p>
            </div>

            <a href="../dashboard.php" class="boton boton-secundario">
                Volver al inicio
            </a>
        </header>

        <?php if (isset($_GET['creado'])): ?>
            <div class="mensaje mensaje-exito">
                Equipo registrado correctamente.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['actualizado'])): ?>
            <div class="mensaje mensaje-exito">
                Equipo actualizado correctamente.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['eliminado'])): ?>
            <div class="mensaje mensaje-exito">
                Equipo eliminado correctamente.
            </div>
        <?php endif; ?>

        <section class="panel-modulo">

            <div class="panel-titulo">
                <h2>Equipos registrados</h2>

                <?php if ($puedeCrear): ?>
                    <a href="crear.php" class="boton boton-principal">
                        + Registrar equipo
                    </a>
                <?php endif; ?>
            </div>

            <form method="GET" action="listar.php" class="formulario-busqueda">

                <input
                    type="text"
                    name="buscar"
                    placeholder="Buscar por nombre o código..."
                    value="<?= htmlspecialchars($buscar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                >

                <button type="submit" class="boton boton-principal">
                    Buscar
                </button>

                <a href="listar.php" class="boton boton-secundario">
                    Limpiar
                </a>

            </form>

            <div class="tabla-contenedor">

                <table class="tabla-estilizada">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($equipos) > 0): ?>

                            <?php foreach ($equipos as $equipo): ?>

                                <tr>

                                    <td>
                                        <?= (int) $equipo['id'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($equipo['codigo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($equipo['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($equipo['categoria'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <span class="estado estado-activo">
                                            <?= htmlspecialchars($equipo['estado'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="acciones-tabla">

                                            <?php if ($puedeEditar): ?>

                                                <a
                                                    href="editar.php?id=<?= (int) $equipo['id'] ?>"
                                                    class="boton boton-pequeno boton-editar"
                                                >
                                                    Editar
                                                </a>

                                            <?php endif; ?>

                                            <?php if ($puedeEliminar): ?>

                                                <form
                                                    method="POST"
                                                    action="eliminar.php"
                                                    class="formulario-accion"
                                                    onsubmit="return confirm('¿Estás seguro de eliminar este equipo?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int) $equipo['id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="boton boton-pequeno boton-desactivar"
                                                    >
                                                        Eliminar
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                        </div>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="sin-resultados">
                                    No se encontraron equipos.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <?php if ($totalPaginas > 0): ?>

                <div class="paginacion">

                    <p>
                        Página <?= $pagina ?> de <?= $totalPaginas ?>
                    </p>

                    <div class="paginacion-botones">

                        <?php if ($pagina > 1): ?>

                            <a
                                href="?pagina=<?= $pagina - 1 ?>&buscar=<?= urlencode($buscar) ?>"
                                class="boton boton-secundario"
                            >
                                Anterior
                            </a>

                        <?php endif; ?>

                        <?php if ($pagina < $totalPaginas): ?>

                            <a
                                href="?pagina=<?= $pagina + 1 ?>&buscar=<?= urlencode($buscar) ?>"
                                class="boton boton-secundario"
                            >
                                Siguiente
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>

        </section>

    </main>

</body>
</html>
