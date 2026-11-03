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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de usuarios | SIGEL</title>
    <link rel="stylesheet" href="/css/estilos.css">
</head>

<body>

    <header class="encabezado">
        <div class="encabezado-contenido">
            <div>
                <h1>SIGEL</h1>
                <p>Sistema de Gestión de Equipos y Laboratorios</p>
            </div>

            <a href="/dashboard.php" class="boton boton-secundario">
                Volver al inicio
            </a>
        </div>
    </header>

    <main class="contenedor contenedor-modulo">

        <section class="titulo-modulo">
            <div>
                <h2>Gestión de usuarios</h2>
                <p>Bienvenido, <?= htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') ?>.</p>
            </div>

            <?php if ($puedeCrear): ?>
                <a href="crear.php" class="boton boton-principal">
                    + Registrar usuario
                </a>
            <?php endif; ?>
        </section>

        <?php if (isset($_GET['creado'])): ?>
            <p class="mensaje mensaje-exito">
                Usuario registrado correctamente.
            </p>
        <?php endif; ?>

        <?php if (isset($_GET['actualizado'])): ?>
            <p class="mensaje mensaje-exito">
                Usuario actualizado correctamente.
            </p>
        <?php endif; ?>

        <?php if (isset($_GET['desactivado'])): ?>
            <p class="mensaje mensaje-exito">
                Usuario desactivado correctamente.
            </p>
        <?php endif; ?>

        <section class="panel-modulo">

            <div class="panel-titulo">
                <div>
                    <h3>Usuarios registrados</h3>
                    <p>Consulta y administra los usuarios del sistema.</p>
                </div>
            </div>

            <form method="GET" action="listar.php" class="formulario-busqueda">

                <input
                    type="text"
                    name="buscar"
                    placeholder="Buscar por nombre o correo..."
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

                                    <td>
                                        <?= htmlspecialchars((string) $usuario['id'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($usuario['correo'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($usuario['rol'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <?php if ($usuario['activo'] == 1): ?>
                                            <span class="estado estado-activo">Activo</span>
                                        <?php else: ?>
                                            <span class="estado estado-inactivo">Inactivo</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="acciones-tabla">

                                        <?php if ($puedeEditar): ?>

                                            <a
                                                href="editar.php?id=<?= (int) $usuario['id'] ?>"
                                                class="boton boton-pequeno boton-editar"
                                            >
                                                Editar
                                            </a>

                                        <?php endif; ?>

                                        <?php if ($puedeDesactivar && $usuario['activo'] == 1): ?>

                                            <form
                                                method="POST"
                                                action="desactivar.php"
                                                class="formulario-accion"
                                                onsubmit="return confirm('¿Deseas desactivar este usuario?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $usuario['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                                                >

                                                <button type="submit" class="boton boton-pequeno boton-desactivar">
                                                    Desactivar
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="sin-resultados">
                                    No se encontraron usuarios.
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