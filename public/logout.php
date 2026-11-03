<?php

require_once __DIR__ . '/../app/includes/auth.php';

// Aceptar solamente solicitudes POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

// Comprobar token CSRF.
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token_logout']) ||
    !hash_equals(
        $_SESSION['csrf_token_logout'],
        $_POST['csrf_token']
    )
) {
    http_response_code(403);
    exit('Solicitud no válida.');
}

// Obtener conexión.
$pdo = require __DIR__ . '/../app/config/config.php';

try {

    // Eliminar token persistente si existe.
    if (isset($_COOKIE['recordarme'])) {

        $partes = explode(':', $_COOKIE['recordarme']);

        if (
            count($partes) === 2 &&
            preg_match('/^[a-f0-9]{24}$/', $partes[0])
        ) {

            $sql = "DELETE FROM auth_tokens
                    WHERE selector = :selector";

            $consulta = $pdo->prepare($sql);

            $consulta->execute([
                'selector' => $partes[0]
            ]);
        }
    }

} catch (Throwable $e) {

    error_log(
        'Error al cerrar sesión: ' . $e->getMessage()
    );

} finally {

    // Eliminar cookie Recordarme.
    eliminarCookieRecordarme();
}

// Vaciar datos de sesión.
$_SESSION = [];

// Eliminar cookie de sesión.
if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax'
        ]
    );
}

// Destruir sesión.
session_destroy();

// Regresar al login.
header('Location: /login.php');
exit;

