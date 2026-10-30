<?php

require_once __DIR__ . '/../../app/includes/auth.php';

// 1. Comprobar que haya iniciado sesión
requiereLogin();

// 2. Comprobar el permiso para crear usuarios
if (!tienePermiso('usuario_crear')) {
    http_response_code(403);
    exit('No tienes permiso para realizar esta acción.');
}

// 3. Aceptar solamente solicitudes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: crear.php');
    exit;
}

// 4. Comprobar el token CSRF
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Solicitud no válida.');
}

// 5. Obtener los datos del formulario
$nombre = trim($_POST['nombre'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$password = $_POST['password'] ?? '';
$rol_id = (int) ($_POST['rol_id'] ?? 0);

// 6. Validar los datos
if ($nombre === '' || $correo === '' || $password === '') {
    exit('Todos los campos son obligatorios.');
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    exit('El correo electrónico no es válido.');
}

if (strlen($password) < 8) {
    exit('La contraseña debe tener al menos 8 caracteres.');
}

if (!in_array($rol_id, [1, 2], true)) {
    exit('El rol seleccionado no es válido.');
}

// 7. Generar el hash de la contraseña
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// 8. Guardar el usuario en la base de datos
try {

    $pdo = require __DIR__ . '/../../app/config/config.php';

    $sql = "INSERT INTO usuarios
            (rol_id, nombre, correo, password_hash, activo)
            VALUES
            (:rol_id, :nombre, :correo, :password_hash, 1)";

    $consulta = $pdo->prepare($sql);

    $consulta->execute([
        'rol_id' => $rol_id,
        'nombre' => $nombre,
        'correo' => $correo,
        'password_hash' => $password_hash
    ]);

    // Eliminar el token utilizado
    unset($_SESSION['csrf_token']);

    header('Location: crear.php?exito=1');
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    if ($e->getCode() === '23000') {
        exit('Ese correo electrónico ya está registrado.');
    }

    http_response_code(500);
    exit('No fue posible registrar el usuario.');
}
