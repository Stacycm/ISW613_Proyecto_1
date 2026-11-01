<?php

require_once __DIR__ . '/../app/includes/auth.php';

// Aceptar solamente solicitudes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Comprobar el token CSRF
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Solicitud no válida.');
}

// Obtener los datos del formulario
$correo = trim($_POST['correo'] ?? '');
$password = $_POST['password'] ?? '';
$recordarme = isset($_POST['recordarme']) &&
              $_POST['recordarme'] === '1';

// Comprobar que los campos no estén vacíos
if ($correo === '' || $password === '') {
    header('Location: login.php?error=1');
    exit;
}

try {

    $pdo = require __DIR__ . '/../app/config/config.php';

    // Buscar al usuario
    $sql = "SELECT id, nombre, correo, password_hash, rol_id
            FROM usuarios
            WHERE correo = :correo AND activo = 1
            LIMIT 1";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['correo' => $correo]);

    $usuario = $consulta->fetch();

    // Verificar las credenciales
    if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
        header('Location: login.php?error=1');
        exit;
    }

    // Regenerar el identificador de sesión
    session_regenerate_id(true);

    // Guardar los datos del usuario
    $_SESSION['id'] = (int) $usuario['id'];
    $_SESSION['nombre'] = $usuario['nombre'];
    $_SESSION['rol_id'] = (int) $usuario['rol_id'];

    // Si seleccionó Recordarme
    if ($recordarme) {

        // Generar selector y validador aleatorios
        $selector = bin2hex(random_bytes(12));
        $validador = bin2hex(random_bytes(32));

        // Crear el hash del validador
        $validadorHash = password_hash(
            $validador,
            PASSWORD_DEFAULT
        );

        // Establecer duración de 30 días
        $expiracion = time() + (30 * 24 * 60 * 60);

        $fechaExpiracion = date(
            'Y-m-d H:i:s',
            $expiracion
        );

        // Guardar el token en la base de datos
        $sqlToken = "INSERT INTO auth_tokens
                     (usuario_id, selector, validador_hash, expira_en)
                     VALUES
                     (:usuario_id, :selector, :validador_hash, :expira_en)";

        $consultaToken = $pdo->prepare($sqlToken);

        $consultaToken->execute([
            'usuario_id' => $usuario['id'],
            'selector' => $selector,
            'validador_hash' => $validadorHash,
            'expira_en' => $fechaExpiracion
        ]);

        // Crear la cookie
        $cookie = $selector . ':' . $validador;

        setcookie('recordarme', $cookie, [
            'expires' => $expiracion,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) &&
                        $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    // Eliminar el token CSRF utilizado
    unset($_SESSION['csrf_token']);

    // Redirigir al panel principal
    header('Location: dashboard.php');
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('No fue posible iniciar sesión. Inténtalo más tarde.');

}


