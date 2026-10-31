<?php

require_once __DIR__ . '/../../app/includes/auth.php';

requiereLogin();

// Verificar permiso para consultar usuarios
if (!tienePermiso('usuario_consultar')) {
    http_response_code(403);
    exit('No tienes permiso para consultar usuarios.');
}

// Conexión a la base de datos
$pdo = require __DIR__ . '/../../app/config/config.php';

// Obtener el texto de búsqueda
$buscar = trim($_GET['buscar'] ?? '');

// Configuración de paginación
$porPagina = 5;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$inicio = ($pagina - 1) * $porPagina;

// Consultar permisos
$puedeCrear = tienePermiso('usuario_crear');
$puedeEditar = tienePermiso('usuario_editar');
$puedeDesactivar = tienePermiso('usuario_desactivar');

// Generar token CSRF para desactivar usuarios
if ($puedeDesactivar && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Contar usuarios para la paginación
$sqlTotal = "SELECT COUNT(*)
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id";

if ($buscar !== '') {
    $sqlTotal .= " WHERE u.nombre LIKE :buscar_nombre
                   OR u.correo LIKE :buscar_correo";
}

$consultaTotal = $pdo->prepare($sqlTotal);

if ($buscar !== '') {
    $consultaTotal->execute([
        'buscar_nombre' => "%$buscar%",
        'buscar_correo' => "%$buscar%"
    ]);
} else {
    $consultaTotal->execute();
}

$totalUsuarios = (int) $consultaTotal->fetchColumn();

$totalPaginas = max(1, (int) ceil($totalUsuarios / $porPagina));

// Evitar páginas que no existen
if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
    $inicio = ($pagina - 1) * $porPagina;
}

// Consultar usuarios
$sql = "SELECT
            u.id,
            u.nombre,
            u.correo,
            u.activo,
            r.nombre AS rol
        FROM usuarios u
        INNER JOIN roles r ON r.id = u.rol_id";

if ($buscar !== '') {
    $sql .= " WHERE u.nombre LIKE :buscar_nombre
              OR u.correo LIKE :buscar_correo";
}

$sql .= " ORDER BY u.id DESC
          LIMIT :limite OFFSET :inicio";

$consulta = $pdo->prepare($sql);

if ($buscar !== '') {
    $consulta->bindValue(':buscar_nombre', "%$buscar%", PDO::PARAM_STR);
    $consulta->bindValue(':buscar_correo', "%$buscar%", PDO::PARAM_STR);
}

$consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$consulta->bindValue(':inicio', $inicio, PDO::PARAM_INT);

$consulta->execute();

$usuarios = $consulta->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Lista de usuarios - SIGEL</title>
</head>

<body>

    <h1>Gestión de usuarios</h1>

    <p>Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?></p>

    <a href="../dashboard.php">Volver al inicio</a>

    <hr>

    <?php if (isset($_GET['creado'])): ?>
        <p>Usuario registrado correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['actualizado'])): ?>
        <p>Usuario actualizado correctamente.</p>
    <?php endif; ?>

    <?php if (isset($_GET['desactivado'])): ?>
        <p>Usuario desactivado correctamente.</p>
    <?php endif; ?>

    <h2>Usuarios registrados</h2>

    <?php if ($puedeCrear): ?>
        <a href="crear.php">Registrar nuevo usuario</a>
    <?php endif; ?>

    <br><br>

    <!-- Formulario de búsqueda -->
    <form method="GET" action="listar.php">

        <input
            type="text"
            name="buscar"
            placeholder="Buscar por nombre o correo"
            value="<?= htmlspecialchars($buscar) ?>"
        >

        <button type="submit">Buscar</button>

        <a href="listar.php">Limpiar búsqueda</a>

    </form>

    <br>

    <!-- Tabla de usuarios -->
    <table border="1" cellpadding="8">

        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>

            <?php if (count($usuarios) > 0): ?>

                <?php foreach ($usuarios as $usuario): ?>

                    <tr>

                        <td><?= htmlspecialchars((string) $usuario['id']) ?></td>

                        <td><?= htmlspecialchars($usuario['nombre']) ?></td>

                        <td><?= htmlspecialchars($usuario['correo']) ?></td>

                        <td><?= htmlspecialchars($usuario['rol']) ?></td>

                        <td>
                            <?php if ($usuario['activo'] == 1): ?>
                                Activo
                            <?php else: ?>
                                Inactivo
                            <?php endif; ?>
                        </td>

                        <td>

                            <!-- Botón editar -->
                            <?php if ($puedeEditar): ?>

                                <a href="editar.php?id=<?= $usuario['id'] ?>">
                                    Editar
                                </a>

                            <?php endif; ?>


                            <!-- Botón desactivar -->
                            <?php if ($puedeDesactivar && $usuario['activo'] == 1): ?>

                                <form
                                    method="POST"
                                    action="desactivar.php"
                                    onsubmit="return confirm('¿Deseas desactivar este usuario?');"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= $usuario['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
                                    >

                                    <button type="submit">
                                        Desactivar
                                    </button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6">No se encontraron usuarios.</td>
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


