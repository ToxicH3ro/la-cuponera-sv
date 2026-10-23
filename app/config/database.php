<?php

declare(strict_types=1);

$configPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

if (!is_file($configPath)) {
    throw new RuntimeException(
        'No se encontró el archivo .env de la aplicación.'
    );
}

$config = parse_ini_file(
    $configPath,
    false,
    INI_SCANNER_RAW
);

if ($config === false) {
    throw new RuntimeException(
        'No se pudo leer el archivo de configuración.'
    );
}

$host = $config['DB_HOST'] ?? '127.0.0.1';
$port = $config['DB_PORT'] ?? '3307';
$name = $config['DB_NAME'] ?? '';
$user = $config['DB_USER'] ?? '';
$password = $config['DB_PASS'] ?? '';
$charset = $config['DB_CHARSET'] ?? 'utf8mb4';

if ($name === '' || $user === '') {
    throw new RuntimeException(
        'Falta configurar DB_NAME o DB_USER.'
    );
}

$dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    error_log(
        'Error de conexión a la base de datos: ' . $e->getMessage()
    );

    throw new RuntimeException(
        'No fue posible conectar con la base de datos.'
    );
}
