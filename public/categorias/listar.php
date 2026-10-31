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
    <title>Gestión de categorías - SIGEL</title>
</head>

<body>

    <h1>Gestión de categorías</h1>

    <a href="../dashboard.php">Volver al inicio</a>

    <hr>

    <?php if (isset($_GET['creado'])): ?>
        <p>Categoría registrada correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['actualizado'])): ?>
        <p>Categoría actualizada correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['eliminado'])): ?>
        <p>Categoría eliminada correctamente.</p>
    <?php endif; ?>

    <h2>Categorías registradas</h2>

    <?php if ($puedeCrear): ?>
        <a href="crear.php">Registrar nueva categoría</a>
    <?php endif; ?>

    <br><br>

    <!-- Formulario de búsqueda -->
    <form method="GET" action="listar.php">

        <input
            type="text"
            name="buscar"
            placeholder="Buscar categoría"
            value="<?= htmlspecialchars($buscar) ?>"
        >

        <button type="submit">Buscar</button>

        <a href="listar.php">Limpiar búsqueda</a>

    </form>

    <br>

    <!-- Tabla de categorías -->
    <table border="1" cellpadding="8">

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
                            <?= htmlspecialchars($categoria['nombre']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars((string) $categoria['dias_max_prestamo']) ?>
                        </td>

                        <td>

                            <!-- Editar categoría -->
                            <?php if ($puedeEditar): ?>

                                <a href="editar.php?id=<?= (int) $categoria['id'] ?>">
                                    Editar
                                </a>

                            <?php endif; ?>


                            <!-- Eliminar categoría -->
                            <?php if ($puedeEliminar): ?>

                                <form
                                    method="POST"
                                    action="eliminar.php"
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
                                        value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"
                                    >

                                    <button type="submit">
                                        Eliminar
                                    </button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="4">No se encontraron categorías.</td>
                </tr>

            <?php endif; ?>

        </tbody>

    </table>

    <br>

    <!-- Paginación -->
    <p>
        Página <?= $pagina ?> de <?= $totalPaginas ?>
    </p>

    <?php if ($pagina > 1): ?>

        <a href="?pagina=<?= $pagina - 1 ?>&buscar=<?= urlencode($buscar) ?>">
            Anterior
        </a>

    <?php endif; ?>

    <?php if ($pagina < $totalPaginas): ?>

        <a href="?pagina=<?= $pagina + 1 ?>&buscar=<?= urlencode($buscar) ?>">
            Siguiente
        </a>

    <?php endif; ?>

</body>
</html>

