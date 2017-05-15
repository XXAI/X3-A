<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::post('obtener-token',    'AutenticacionController@autenticar');
Route::post('refresh-token',    'AutenticacionController@refreshToken');
Route::get('check-token',       'AutenticacionController@verificar');

Route::get('generar-excel-pedido/{id}', 'PedidoController@generarExcel');

Route::group(['middleware' => 'jwt'], function () {
    Route::resource('usuarios', 'UsuarioController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('roles', 'RolController',           ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('permisos', 'PermisoController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    

    Route::resource('jurisdicciones', 'JurisdiccionesController',    ['only' => ['index']]);
    Route::resource('unidades-medicas', 'UnidadesMedicasController',    ['only' => ['index']]);
    Route::resource('almacenes',        'AlmacenController',    ['only' => ['index']]);

    Route::resource('proveedores', 'ProveedoresController',    ['only' => ['index']]);

    
    // # SECCION: Administrador central
    // Akira: Debería haber creado un  grupo para la ruta pero ya no me dió tiempo.
    Route::get('abasto', 'AbastoController@lista');
    Route::get('abasto-excel', 'AbastoController@excel');
    Route::get('presupuesto-pedidos-administrador-central', 'PedidosAdministradorCentralController@presupuesto');
    Route::get('pedidos-administrador-central', 'PedidosAdministradorCentralController@lista');
    Route::get('pedidos-administrador-central-excel', 'PedidosAdministradorCentralController@excel');
    // # FIN SECCION

    Route::group(['middleware' => 'almacen'], function () {
        Route::resource('entregas',         'EntregaController',  ['only' => ['index', 'show', 'store','update','destroy']]);
        Route::get('entregas-stats',        'EntregaController@stats');

        //Pedidos
        Route::resource('pedidos',          'PedidoController',     ['only' => ['index', 'show', 'store','update','destroy']]);
        Route::get('pedidos-stats',         'PedidoController@stats');
        Route::get('pedidos-presupuesto',   'PedidoController@obtenerDatosPresupuesto');

        Route::resource('recepcion-pedido', 'RecepcionPedidoController',    ['only' => ['show', 'update','destroy']]);

        //Ruta para listado de medicamentos a travez de un autocomplete, soporta paginación y busqueda
        Route::resource('catalogo-insumos',  'CatalogoInsumoController',     ['only' => ['index', 'show']]);
    });
    Route::resource('movimientos',    'MovimientoController',    ['only' => ['index', 'show', 'store','update','destroy']]);

 
    Route::resource('stock',    'StockController',    ['only' => ['index']]);
    Route::resource('comprobar-stock',    'ComprobarStockController',    ['only' => ['index']]);

    Route::resource('receta',           'RecetaController',             ['only' => ['index', 'show', 'store','update','destroy']]);

    Route::group(['prefix' => 'sync','namespace' => 'Sync'], function () {
        Route::get('manual',    'SincronizacionController@manual');        
        Route::get('auto',      'SincronizacionController@auto');
        Route::post('importar', 'SincronizacionController@importarSync');
        Route::post('confirmar', 'SincronizacionController@confirmarSync');
    });
    
});
