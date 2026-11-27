<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config/database.php';
require_once dirname(__DIR__) . '/app/helpers/auth.php';
require_once dirname(__DIR__) . '/app/helpers/filtros_listado_usuarios.php';
require_once dirname(__DIR__) . '/app/controllers/AdminController.php';

iniciarSesion();

// Verificar autenticación.
if (!estaAutenticado()) {
    header('Location: login.php');
    exit;
}

// Solo administradores pueden consultar el listado.
if (!tieneRol('admin')) {
    http_response_code(403);
    exit('No tienes permisos para consultar el listado de usuarios.');
}

// Obtener y validar filtros.
$filtros = obtenerFiltrosListadoUsuarios(true);

$busqueda = $filtros['busqueda'];
$filtroRol = $filtros['filtroRol'];
$filtroEstado = $filtros['filtroEstado'];

$usuarios = [];
$mensajeError = '';

try {

    $controlador = new AdminController($pdo);

    $usuarios = $controlador->listarUsuarios(
        $filtros['rolConsulta'],
        $filtros['estadoConsulta'],
        $busqueda
    );

} catch (Throwable $e) {

    error_log(
        '[La Cuponera SV] Error al listar usuarios: '
        . $e->getMessage()
    );

    $mensajeError = 'No fue posible cargar el listado de usuarios.';
}

// Configuración de la vista.
$tituloPagina = 'Usuarios registrados';

$descripcionPagina =
    'Consulta las cuentas de clientes, empresas y administradores '
    . 'registrados en La Cuponera SV.';

$accionFormulario = 'usuarios.php';
$mostrarFiltroRol = true;
$etiquetaConteo = 'Usuarios encontrados';

require dirname(__DIR__) . '/app/views/admin/listado-usuarios.php';