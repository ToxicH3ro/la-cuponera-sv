<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/helpers/auth.php';

iniciarSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido.');
}

cerrarSesion();

header('Location: login.php');
exit;
