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

// Comprobar que los campos no estén vacíos
if ($correo === '' || $password === '') {
    header('Location: login.php?error=1');
    exit;
}

// Buscar al usuario
try {
    $pdo = require __DIR__ . '/../app/config/config.php';

    $sql = "SELECT id, nombre, correo, password_hash, rol_id
            FROM usuarios
            WHERE correo = :correo AND activo = 1
            LIMIT 1";

    $consulta = $pdo->prepare($sql);
    $consulta->execute(['correo' => $correo]);

    $usuario = $consulta->fetch();

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('No fue posible iniciar sesión. Inténtalo más tarde.');
}

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

// Eliminar el token utilizado
unset($_SESSION['csrf_token']);

// Redirigir al panel principal
header('Location: dashboard.php');
exit;

