<?php

require_once __DIR__ . '/../app/includes/auth.php';

// Obtener la conexión a la base de datos
$pdo = require __DIR__ . '/../app/config/config.php';

// Si existe la cookie Recordarme, eliminar su token
if (isset($_COOKIE['recordarme'])) {

    $partes = explode(':', $_COOKIE['recordarme'], 2);

    if (
        count($partes) === 2 &&
        preg_match('/^[a-f0-9]{24}$/', $partes[0])
    ) {

        $sql = "DELETE FROM auth_tokens
                WHERE selector = :selector
                AND usuario_id = :usuario_id";

        $consulta = $pdo->prepare($sql);

        $consulta->execute([
            'selector' => $partes[0],
            'usuario_id' => $_SESSION['id'] ?? 0
        ]);
    }
}

// Eliminar la cookie Recordarme
setcookie('recordarme', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) &&
                $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Eliminar todos los datos de la sesión
$_SESSION = [];

// Eliminar la cookie de sesión
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

// Destruir la sesión
session_destroy();

// Regresar al login
header('Location: login.php');
exit;
