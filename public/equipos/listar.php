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

<h2>Lista de equipos</h2>

<?php if (isset($_GET['creado'])): ?>
    <p>Equipo registrado correctamente.</p>
<?php endif; ?>

<?php if (isset($_GET['actualizado'])): ?>
    <p>Equipo actualizado correctamente.</p>
<?php endif; ?>

<?php if (isset($_GET['eliminado'])): ?>
    <p>Equipo eliminado correctamente.</p>
<?php endif; ?>

<!-- Formulario de búsqueda -->
<form method="GET">

    <input
        type="text"
        name="buscar"
        placeholder="Buscar por nombre o código"
        value="<?= htmlspecialchars($buscar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
    >

    <button type="submit">Buscar</button>

    <a href="listar.php">Limpiar búsqueda</a>

</form>

<br>

<!-- Botón para registrar -->
<?php if ($puedeCrear): ?>
    <a href="crear.php">Registrar equipo</a>
<?php endif; ?>

<br><br>

<table border="1" cellpadding="8">

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

                    <td><?= (int) $equipo['id'] ?></td>

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
                        <?= htmlspecialchars($equipo['estado'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </td>

                    <td>

                        <?php if ($puedeEditar): ?>

                            <a href="editar.php?id=<?= (int) $equipo['id'] ?>">
                                Editar
                            </a>

                        <?php endif; ?>

                        <?php if ($puedeEliminar): ?>

                            <form
                                method="POST"
                                action="eliminar.php"
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
                <td colspan="6">
                    No se encontraron equipos.
                </td>
            </tr>

        <?php endif; ?>

    </tbody>

</table>

<br>

<!-- Navegación de páginas -->
<?php if ($totalPaginas > 1): ?>

    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>

        <a href="?pagina=<?= $i ?>&buscar=<?= urlencode($buscar) ?>">
            <?= $i ?>
        </a>

    <?php endfor; ?>

<?php endif; ?>

<br><br>

<a href="../dashboard.php">Volver al inicio</a>

