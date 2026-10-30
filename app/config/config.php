<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "sigel";
$port = 3306;

return new PDO(
    "mysql:host=$host;dbname=$database;port=$port;charset=utf8mb4",
    $user,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);
