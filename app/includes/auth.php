<?php

// Iniciar la sesión si todavía no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Recuperar la sesión mediante la cookie "Recordarme"
if (
    !isset($_SESSION['id']) &&
    isset($_COOKIE['recordarme'])
) {

    try {

        $cookie = $_COOKIE['recordarme'];

        // Separar selector y validador
        $partes = explode(':', $cookie, 2);

        if (count($partes) === 2) {

            $selector = $partes[0];
            $validador = $partes[1];

            // Comprobar el formato de los valores
            if (
                preg_match('/^[a-f0-9]{24}$/', $selector) &&
                preg_match('/^[a-f0-9]{64}$/', $validador)
            ) {

                $pdo = require __DIR__ . '/../config/config.php';

                // Buscar el token y su usuario
                $sql = "SELECT
                            at.id AS token_id,
                            at.usuario_id,
                            at.validador_hash,
                            u.nombre,
                            u.rol_id
                        FROM auth_tokens at
                        INNER JOIN usuarios u
                            ON u.id = at.usuario_id
                        WHERE at.selector = :selector
                        AND at.expira_en > NOW()
                        AND u.activo = 1
                        LIMIT 1";

                $consulta = $pdo->prepare($sql);

                $consulta->execute([
                    'selector' => $selector
                ]);

                $token = $consulta->fetch();

                // Verificar el validador
                if (
                    $token &&
                    password_verify(
                        $validador,
                        $token['validador_hash']
                    )
                ) {

                    // Regenerar el identificador de sesión
                    session_regenerate_id(true);

                    // Recuperar los datos del usuario
                    $_SESSION['id'] = (int) $token['usuario_id'];
                    $_SESSION['nombre'] = $token['nombre'];
                    $_SESSION['rol_id'] = (int) $token['rol_id'];

                } else {

                    // Eliminar el token inválido o vencido
                    $sqlEliminar = "DELETE FROM auth_tokens
                                    WHERE selector = :selector";

                    $consultaEliminar = $pdo->prepare($sqlEliminar);

                    $consultaEliminar->execute([
                        'selector' => $selector
                    ]);

                    // Eliminar la cookie
                    setcookie('recordarme', '', [
                        'expires' => time() - 3600,
                        'path' => '/',
                        'secure' => !empty($_SERVER['HTTPS']) &&
                                    $_SERVER['HTTPS'] !== 'off',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);

                }
            }
        }

    } catch (PDOException $e) {

        error_log($e->getMessage());

    }
}

// Comprobar que el usuario haya iniciado sesión
function requiereLogin(): void
{
    if (!isset($_SESSION['id'])) {
        header('Location: /login.php');
        exit;
    }
}

// Comprobar los permisos del usuario
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
