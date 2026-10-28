
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requiereLogin(): void
{
    if (!isset($_SESSION['id'])) {
        header('Location: /login.php');
        exit;
    }
}
