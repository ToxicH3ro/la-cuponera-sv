<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Rutas web de La Cuponera SV
|--------------------------------------------------------------------------
|
| Cada ruta apunta a un método que ya existe en su controlador.
| Las rutas de presentación se agregarán cuando implementemos
| las vistas y los métodos correspondientes.
|
*/

return [

    'GET' => [

        '/' => [
            'controller' => 'OfertaController',
            'method' => 'listarCatalogo',
        ],

        '/ofertas' => [
            'controller' => 'OfertaController',
            'method' => 'listarCatalogo',
        ],

        '/cliente/compras' => [
            'controller' => 'ClienteController',
            'method' => 'historialCompras',
            'role' => 'cliente',
        ],

        '/empresa/ofertas' => [
            'controller' => 'EmpresaController',
            'method' => 'listarMisOfertas',
            'role' => 'empresa',
        ],

    ],

    'POST' => [

        '/login' => [
            'controller' => 'AuthController',
            'method' => 'autenticar',
        ],

    ],

];
