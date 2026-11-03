<?php

// ==========================================
// CONFIGURACIÓN DE SESIÓN
// ==========================================

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) &&
                    $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// ==========================================
// FUNCIONES PARA COOKIES
// ==========================================

function eliminarCookieRecordarme(): void
{
    setcookie('recordarme', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) &&
                    $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    unset($_COOKIE['recordarme']);
}

// ==========================================
// RESTAURAR SESIÓN CON RECORDARME
// ==========================================

if (!isset($_SESSION['id']) && isset($_COOKIE['recordarme'])) {

    $partes = explode(':', $_COOKIE['recordarme']);

    if (
        count($partes) === 2 &&
        preg_match('/^[a-f0-9]{24}$/', $partes[0]) &&
        preg_match('/^[a-f0-9]{64}$/', $partes[1])
    ) {

        [$selector, $validador] = $partes;

        try {

            $pdo = require __DIR__ . '/../config/config.php';

            $sql = "SELECT
                        t.id AS token_id,
                        t.usuario_id,
                        t.validador_hash,
                        u.nombre,
                        u.rol_id
                    FROM auth_tokens t
                    INNER JOIN usuarios u ON u.id = t.usuario_id
                    WHERE t.selector = :selector
                    AND t.expira_en > NOW()
                    AND u.activo = 1";

            $consulta = $pdo->prepare($sql);

            $consulta->execute([
                'selector' => $selector
            ]);

            $token = $consulta->fetch();

            if (
                $token &&
                password_verify($validador, $token['validador_hash'])
            ) {

                $pdo->beginTransaction();

                // Generar un nuevo token.
                $nuevoSelector = bin2hex(random_bytes(12));
                $nuevoValidador = bin2hex(random_bytes(32));

                $nuevoHash = password_hash(
                    $nuevoValidador,
                    PASSWORD_DEFAULT
                );

                $expiracion = time() + (30 * 24 * 60 * 60);

                $fechaExpiracion = date(
                    'Y-m-d H:i:s',
                    $expiracion
                );

                // Guardar el nuevo token.
                $insertar = $pdo->prepare(
                    "INSERT INTO auth_tokens
                    (usuario_id, selector, validador_hash, expira_en)
                    VALUES
                    (:usuario_id, :selector, :hash, :expira)"
                );

                $insertar->execute([
                    'usuario_id' => $token['usuario_id'],
                    'selector' => $nuevoSelector,
                    'hash' => $nuevoHash,
                    'expira' => $fechaExpiracion
                ]);

                // Eliminar el token anterior.
                $eliminar = $pdo->prepare(
                    "DELETE FROM auth_tokens WHERE id = :id"
                );

                $eliminar->execute([
                    'id' => $token['token_id']
                ]);

                $pdo->commit();

                // Crear la sesión.
                session_regenerate_id(true);

                $_SESSION['id'] = (int) $token['usuario_id'];
                $_SESSION['nombre'] = $token['nombre'];
                $_SESSION['rol_id'] = (int) $token['rol_id'];

                // Actualizar la cookie.
                setcookie(
                    'recordarme',
                    $nuevoSelector . ':' . $nuevoValidador,
                    [
                        'expires' => $expiracion,
                        'path' => '/',
                        'secure' => !empty($_SERVER['HTTPS']) &&
                                    $_SERVER['HTTPS'] !== 'off',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]
                );

            } else {

                // Eliminar token inválido o expirado.
                $eliminar = $pdo->prepare(
                    "DELETE FROM auth_tokens WHERE selector = :selector"
                );

                $eliminar->execute([
                    'selector' => $selector
                ]);

                eliminarCookieRecordarme();
            }

        } catch (Throwable $e) {

            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Error al restaurar sesión: ' . $e->getMessage()
            );
        }

    } else {

        // Cookie con formato incorrecto.
        eliminarCookieRecordarme();
    }
}

// ==========================================
// FUNCIÓN PARA REQUERIR INICIO DE SESIÓN
// ==========================================

function requiereLogin(): void
{
    if (!isset($_SESSION['id'])) {
        header('Location: /login.php');
        exit;
    }
}

// ==========================================
// FUNCIÓN PARA COMPROBAR PERMISOS
// ==========================================

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

