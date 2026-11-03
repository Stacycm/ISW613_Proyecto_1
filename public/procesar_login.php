<?php

require_once __DIR__ . '/../app/includes/auth.php';

// Aceptar solamente solicitudes POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login.php');
    exit;
}

// Si ya inició sesión, no procesar otro login.
if (isset($_SESSION['id'])) {
    header('Location: /dashboard.php');
    exit;
}

// Comprobar el token CSRF.
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Solicitud no válida.');
}

// Obtener los datos.
$correo = trim($_POST['correo'] ?? '');
$password = $_POST['password'] ?? '';

$recordarme = isset($_POST['recordarme']) &&
              $_POST['recordarme'] === '1';

// Comprobar campos vacíos.
if ($correo === '' || $password === '') {
    header('Location: /login.php?error=1');
    exit;
}

try {

    $pdo = require __DIR__ . '/../app/config/config.php';

    // Buscar usuario activo.
    $sql = "SELECT id, nombre, correo, password_hash, rol_id
            FROM usuarios
            WHERE correo = :correo
            AND activo = 1
            LIMIT 1";

    $consulta = $pdo->prepare($sql);

    $consulta->execute([
        'correo' => $correo
    ]);

    $usuario = $consulta->fetch();

    // Verificar credenciales.
    if (
        !$usuario ||
        !password_verify($password, $usuario['password_hash'])
    ) {
        header('Location: /login.php?error=1');
        exit;
    }

    // Actualizar hash si la configuración cambió.
    if (password_needs_rehash(
        $usuario['password_hash'],
        PASSWORD_DEFAULT
    )) {

        $nuevoHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $actualizar = $pdo->prepare(
            "UPDATE usuarios
             SET password_hash = :hash
             WHERE id = :id"
        );

        $actualizar->execute([
            'hash' => $nuevoHash,
            'id' => $usuario['id']
        ]);
    }

    // Gestionar Recordarme.
    if ($recordarme) {

        $selector = bin2hex(random_bytes(12));
        $validador = bin2hex(random_bytes(32));

        $validadorHash = password_hash(
            $validador,
            PASSWORD_DEFAULT
        );

        $expiracion = time() + (30 * 24 * 60 * 60);

        $fechaExpiracion = date(
            'Y-m-d H:i:s',
            $expiracion
        );

        $pdo->beginTransaction();

        // Eliminar tokens anteriores de este navegador,
        // si existe una cookie previa válida.
        if (isset($_COOKIE['recordarme'])) {

            $partes = explode(':', $_COOKIE['recordarme']);

            if (
                count($partes) === 2 &&
                preg_match('/^[a-f0-9]{24}$/', $partes[0])
            ) {

                $eliminar = $pdo->prepare(
                    "DELETE FROM auth_tokens
                     WHERE selector = :selector"
                );

                $eliminar->execute([
                    'selector' => $partes[0]
                ]);
            }
        }

        // Insertar nuevo token.
        $sqlToken = "INSERT INTO auth_tokens
                     (usuario_id, selector, validador_hash, expira_en)
                     VALUES
                     (:usuario_id, :selector, :hash, :expira)";

        $consultaToken = $pdo->prepare($sqlToken);

        $consultaToken->execute([
            'usuario_id' => $usuario['id'],
            'selector' => $selector,
            'hash' => $validadorHash,
            'expira' => $fechaExpiracion
        ]);

        $pdo->commit();

        // Crear cookie.
        setcookie(
            'recordarme',
            $selector . ':' . $validador,
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

        // Si no seleccionó Recordarme, eliminar token anterior.
        if (isset($_COOKIE['recordarme'])) {

            $partes = explode(':', $_COOKIE['recordarme']);

            if (
                count($partes) === 2 &&
                preg_match('/^[a-f0-9]{24}$/', $partes[0])
            ) {

                $eliminar = $pdo->prepare(
                    "DELETE FROM auth_tokens
                     WHERE selector = :selector"
                );

                $eliminar->execute([
                    'selector' => $partes[0]
                ]);
            }
        }

        eliminarCookieRecordarme();
    }

    // Regenerar identificador de sesión.
    session_regenerate_id(true);

    // Guardar información del usuario.
    $_SESSION['id'] = (int) $usuario['id'];
    $_SESSION['nombre'] = $usuario['nombre'];
    $_SESSION['rol_id'] = (int) $usuario['rol_id'];

    // Eliminar token CSRF utilizado.
    unset($_SESSION['csrf_token']);

    // Redirigir al dashboard.
    header('Location: /dashboard.php');
    exit;

} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Error al iniciar sesión: ' . $e->getMessage()
    );

    http_response_code(500);
    exit('No fue posible iniciar sesión. Inténtalo más tarde.');
}


