<?php

require_once __DIR__ . '/../../app/includes/auth.php';

// Verificar que haya iniciado sesión
requiereLogin();

// Verificar permiso para desactivar usuarios
if (!tienePermiso('usuario_desactivar')) {
    http_response_code(403);
    exit('No tienes permiso para desactivar usuarios.');
}

// Aceptar solamente solicitudes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
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

// Obtener el ID del usuario
$id = (int) ($_POST['id'] ?? 0);

// Validar el ID
if ($id <= 0) {
    exit('Usuario no válido.');
}

// Evitar que el usuario desactive su propia cuenta
if ($id === (int) $_SESSION['id']) {
    exit('No puedes desactivar tu propia cuenta.');
}

try {

    // Conectar a la base de datos
    $pdo = require __DIR__ . '/../../app/config/config.php';

    // Desactivar el usuario
    $sql = "UPDATE usuarios
            SET activo = 0
            WHERE id = :id AND activo = 1";

    $consulta = $pdo->prepare($sql);

    $consulta->execute([
        'id' => $id
    ]);

    // Redirigir al listado
    header('Location: listar.php?desactivado=1');
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);
    exit('No fue posible desactivar el usuario.');
}
