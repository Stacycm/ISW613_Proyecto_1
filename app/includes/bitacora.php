<?php

function registrarBitacora(
    PDO $pdo,
    string $accion,
    string $entidad,
    ?int $entidadId = null,
    ?array $valoresAntes = null,
    ?array $valoresDespues = null
): void {

    // Obtener el usuario que realizó la acción
    $usuarioId = $_SESSION['id'] ?? null;

    // Obtener la dirección IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    // Convertir los valores anteriores a JSON
    $antes = $valoresAntes !== null
        ? json_encode($valoresAntes, JSON_UNESCAPED_UNICODE)
        : null;

    // Convertir los valores nuevos a JSON
    $despues = $valoresDespues !== null
        ? json_encode($valoresDespues, JSON_UNESCAPED_UNICODE)
        : null;

    // Insertar el registro en la bitácora
    $sql = "INSERT INTO bitacora (
                usuario_id,
                accion,
                entidad,
                entidad_id,
                valores_antes,
                valores_despues,
                ip
            ) VALUES (
                :usuario_id,
                :accion,
                :entidad,
                :entidad_id,
                :valores_antes,
                :valores_despues,
                :ip
            )";

    $consulta = $pdo->prepare($sql);

    $consulta->execute([
        'usuario_id' => $usuarioId,
        'accion' => $accion,
        'entidad' => $entidad,
        'entidad_id' => $entidadId,
        'valores_antes' => $antes,
        'valores_despues' => $despues,
        'ip' => $ip
    ]);
}
