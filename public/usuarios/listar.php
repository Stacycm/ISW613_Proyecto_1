<?php

require_once __DIR__ . '/../../app/includes/auth.php';

// Verificar que haya iniciado sesión
requiereLogin();

// Verificar permiso para consultar usuarios
if (!tienePermiso('usuario_consultar')) {
    http_response_code(403);
    exit('No tienes permiso para consultar usuarios.');
}

// Conectar a la base de datos
$pdo = require __DIR__ . '/../../app/config/config.php';

// Obtener el texto de búsqueda
$busqueda = trim($_GET['buscar'] ?? '');

// Configuración de paginación
$porPagina = 5;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$inicio = ($pagina - 1) * $porPagina;

// Contar los usuarios que coinciden con la búsqueda
$sqlConteo = "SELECT COUNT(*)
              FROM usuarios
              WHERE nombre LIKE :buscar_nombre
              OR correo LIKE :buscar_correo";

$consultaConteo = $pdo->prepare($sqlConteo);

$consultaConteo->execute([
    'buscar_nombre' => "%$busqueda%",
    'buscar_correo' => "%$busqueda%"
]);

$totalUsuarios = (int) $consultaConteo->fetchColumn();

// Calcular el total de páginas
$totalPaginas = max(1, (int) ceil($totalUsuarios / $porPagina));

// Consultar los usuarios de la página actual
$sql = "SELECT id, nombre, correo, rol_id, activo
        FROM usuarios
        WHERE nombre LIKE :buscar_nombre
        OR correo LIKE :buscar_correo
        ORDER BY id DESC
        LIMIT :inicio, :cantidad";

$consulta = $pdo->prepare($sql);

$consulta->bindValue(':buscar_nombre', "%$busqueda%", PDO::PARAM_STR);
$consulta->bindValue(':buscar_correo', "%$busqueda%", PDO::PARAM_STR);
$consulta->bindValue(':inicio', $inicio, PDO::PARAM_INT);
$consulta->bindValue(':cantidad', $porPagina, PDO::PARAM_INT);

$consulta->execute();

$usuarios = $consulta->fetchAll();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios | SIGEL</title>
</head>

<body>

    <h1>Gestión de usuarios</h1>

    <a href="crear.php">Registrar nuevo usuario</a>

    <h2>Buscar usuarios</h2>

    <form method="GET" action="listar.php">

        <input
            type="text"
            name="buscar"
            placeholder="Buscar por nombre o correo"
            value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>"
        >

        <button type="submit">Buscar</button>

        <a href="listar.php">Limpiar búsqueda</a>

    </form>

    <h2>Usuarios registrados</h2>

    <?php if (count($usuarios) > 0): ?>

        <table border="1" cellpadding="8">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($usuarios as $usuario): ?>

                    <tr>

                        <td>
                            <?= (int) $usuario['id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($usuario['correo'], ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td>
                            <?= $usuario['rol_id'] == 1 ? 'Administrador' : 'Operador' ?>
                        </td>

                        <td>
                            <?= $usuario['activo'] == 1 ? 'Activo' : 'Inactivo' ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php else: ?>

        <p>No se encontraron usuarios.</p>

    <?php endif; ?>

    <p>
        Página <?= $pagina ?> de <?= $totalPaginas ?>
    </p>

    <?php if ($pagina > 1): ?>

        <a href="?pagina=<?= $pagina - 1 ?>&buscar=<?= urlencode($busqueda) ?>">
            Anterior
        </a>

    <?php endif; ?>

    <?php if ($pagina < $totalPaginas): ?>

        <a href="?pagina=<?= $pagina + 1 ?>&buscar=<?= urlencode($busqueda) ?>">
            Siguiente
        </a>

    <?php endif; ?>

    <br><br>

    <a href="../dashboard.php">Volver al panel principal</a>

</body>

</html>
