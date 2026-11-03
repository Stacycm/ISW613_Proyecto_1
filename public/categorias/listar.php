<?php

require_once __DIR__ . '/../../app/includes/auth.php';

requiereLogin();

// Verificar permiso para consultar categorías
if (!tienePermiso('categoria_consultar')) {
    http_response_code(403);
    exit('No tienes permiso para consultar categorías.');
}

// Conexión a la base de datos
$pdo = require __DIR__ . '/../../app/config/config.php';

// Búsqueda
$buscar = trim($_GET['buscar'] ?? '');

// Permisos
$puedeCrear = tienePermiso('categoria_crear');
$puedeEditar = tienePermiso('categoria_editar');
$puedeEliminar = tienePermiso('categoria_eliminar');

// Generar token CSRF para eliminar
if ($puedeEliminar && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Paginación
$porPagina = 5;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$inicio = ($pagina - 1) * $porPagina;

// Contar categorías no eliminadas
$sqlTotal = "SELECT COUNT(*)
             FROM categoria
             WHERE eliminado_en IS NULL";

if ($buscar !== '') {
    $sqlTotal .= " AND nombre LIKE :buscar";
}

$consultaTotal = $pdo->prepare($sqlTotal);

if ($buscar !== '') {
    $consultaTotal->execute([
        'buscar' => "%$buscar%"
    ]);
} else {
    $consultaTotal->execute();
}

$totalCategorias = (int) $consultaTotal->fetchColumn();

$totalPaginas = max(1, (int) ceil($totalCategorias / $porPagina));

// Evitar páginas inexistentes
if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
    $inicio = ($pagina - 1) * $porPagina;
}

// Consultar categorías
$sql = "SELECT id, nombre, dias_max_prestamo
        FROM categoria
        WHERE eliminado_en IS NULL";

if ($buscar !== '') {
    $sql .= " AND nombre LIKE :buscar";
}

$sql .= " ORDER BY id DESC
          LIMIT :limite OFFSET :inicio";

$consulta = $pdo->prepare($sql);

if ($buscar !== '') {
    $consulta->bindValue(':buscar', "%$buscar%", PDO::PARAM_STR);
}

$consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$consulta->bindValue(':inicio', $inicio, PDO::PARAM_INT);

$consulta->execute();

$categorias = $consulta->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de categorías - SIGEL</title>
    <link rel="stylesheet" href="/css/estilos.css">
</head>

<body>

    <main class="contenedor contenedor-modulo">

        <header class="titulo-modulo">
            <div>
                <h1>Gestión de categorías</h1>
                <p>Consulta y administra las categorías de los equipos.</p>
            </div>

            <a href="../dashboard.php" class="boton boton-secundario">
                Volver al inicio
            </a>
        </header>

        <?php if (isset($_GET['creado'])): ?>
            <div class="mensaje mensaje-exito">
                Categoría registrada correctamente.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['actualizado'])): ?>
            <div class="mensaje mensaje-exito">
                Categoría actualizada correctamente.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['eliminado'])): ?>
            <div class="mensaje mensaje-exito">
                Categoría eliminada correctamente.
            </div>
        <?php endif; ?>

        <section class="panel-modulo">

            <div class="panel-titulo">
                <h2>Categorías registradas</h2>

                <?php if ($puedeCrear): ?>
                    <a href="crear.php" class="boton boton-principal">
                        + Registrar categoría
                    </a>
                <?php endif; ?>
            </div>

            <form method="GET" action="listar.php" class="formulario-busqueda">

                <input
                    type="text"
                    name="buscar"
                    placeholder="Buscar categoría..."
                    value="<?= htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8') ?>"
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
                            <th>Nombre</th>
                            <th>Días máximos de préstamo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($categorias) > 0): ?>

                            <?php foreach ($categorias as $categoria): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars((string) $categoria['id']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars((string) $categoria['dias_max_prestamo']) ?>
                                    </td>

                                    <td>
                                        <div class="acciones-tabla">

                                            <?php if ($puedeEditar): ?>

                                                <a
                                                    href="editar.php?id=<?= (int) $categoria['id'] ?>"
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
                                                    onsubmit="return confirm('¿Deseas eliminar esta categoría?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int) $categoria['id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
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
                                <td colspan="4" class="sin-resultados">
                                    No se encontraron categorías.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

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

        </section>

    </main>

</body>
</html>
