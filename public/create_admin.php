<?php
require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/models/Usuario.php';

$datos = [
    'usuario' => 'admin_ga181200',
    'correo' => 'admin@admin.udb.edu.sv',
    'contrasena' => 'hola12345678',
    'rol' => 'admin'
];

$new_Admin = new usuario($pdo);

$pdo->beginTransaction();

$new_admin_id = $new_Admin->crear($datos['usuario'], $datos['correo'],
$datos['contrasena'], $datos['rol']);

$pdo->commit();

echo $new_admin_id
?>