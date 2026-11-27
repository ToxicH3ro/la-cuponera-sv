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

// Solo administradores pueden consultar este listado.
if (!tieneRol('admin')) {
    http_response_code(403);
    exit('No tienes permisos para consultar el listado de administradores.');
}

// Obtener filtros.
// El rol se fuerza a "admin" desde el servidor.
$filtros = obtenerFiltrosListadoUsuarios(false);

$busqueda = $filtros['busqueda'];
$filtroEstado = $filtros['filtroEstado'];

$usuarios = [];
$mensajeError = '';

try {

    $controlador = new AdminController($pdo);

    $usuarios = $controlador->listarUsuarios(
        'admin',
        $filtros['estadoConsulta'],
        $busqueda
    );

} catch (Throwable $e) {

    error_log(
        '[La Cuponera SV] Error al listar administradores: '
        . $e->getMessage()
    );

    $mensajeError = 'No fue posible cargar el listado de administradores.';
}

// Configuración de la vista.
$tituloPagina = 'Administradores registrados';

$descripcionPagina =
    'Consulta las cuentas de los administradores registrados '
    . 'en La Cuponera SV.';

$accionFormulario = 'administradores.php';
$mostrarFiltroRol = false;
$filtroRol = 'admin';
$etiquetaConteo = 'Administradores encontrados';

require dirname(__DIR__) . '/app/views/admin/listado-usuarios.php';