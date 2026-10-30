
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requiereLogin(): void
{
    if (!isset($_SESSION['id'])) {
        header('Location: /login.php');
        exit;
    }
}

function tienePermiso(string $permiso): bool
{
    requiereLogin();

    $pdo = require __DIR__ . '/../config/config.php';

    $sql = "SELECT COUNT(*)
            FROM rol_permiso rp
            INNER JOIN permisos p ON p.id = rp.permiso_id
            WHERE rp.rol_id = :rol_id
            AND p.clave = :permiso";

    $consulta = $pdo->prepare($sql);

    $consulta->execute([
        'rol_id' => $_SESSION['rol_id'],
        'permiso' => $permiso
    ]);

    return (int) $consulta->fetchColumn() > 0;
}
