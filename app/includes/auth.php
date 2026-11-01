<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// RESTAURAR SESIÓN CON "RECORDARME"
// ==========================================

if (!isset($_SESSION['id']) && isset($_COOKIE['recordarme'])) {

    $partes = explode(':', $_COOKIE['recordarme']);

    if (count($partes) === 2) {

        [$selector, $validador] = $partes;

        if (
            preg_match('/^[a-f0-9]{24}$/', $selector) &&
            preg_match('/^[a-f0-9]{64}$/', $validador)
        ) {
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

                    try {

                        // Iniciar transacción.
                        $pdo->beginTransaction();

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

                        // Confirmar los cambios.
                        $pdo->commit();

                        // Crear la sesión.
                        session_regenerate_id(true);

                        $_SESSION['id'] = $token['usuario_id'];
                        $_SESSION['nombre'] = $token['nombre'];
                        $_SESSION['rol_id'] = $token['rol_id'];

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

                    } catch (PDOException $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        error_log(
                            'Error al renovar el token: ' . $e->getMessage()
                        );
                    }

                } else {

                    // Eliminar token inválido o expirado.
                    $eliminar = $pdo->prepare(
                        "DELETE FROM auth_tokens WHERE selector = :selector"
                    );

                    $eliminar->execute([
                        'selector' => $selector
                    ]);

                    // Eliminar cookie.
                    setcookie('recordarme', '', [
                        'expires' => time() - 3600,
                        'path' => '/',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }

            } catch (PDOException $e) {

                error_log(
                    'Error al restaurar sesión: ' . $e->getMessage()
                );
            }
        }
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
