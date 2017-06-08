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

Route::get('grupo-permiso',       'AutoCompleteController@grupo_permiso');
Route::get('clues-auto',          'AutoCompleteController@clues');
Route::get('insumos-auto',          'AutoCompleteController@insumos');



    
Route::group(['middleware' => 'jwt'], function () {
    Route::resource('usuarios', 'UsuarioController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('roles', 'RolController',           ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('permisos', 'PermisoController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    

    //Route::resource('unidades-medicas', 'UnidadesMedicasController',    ['only' => ['index']]);
    Route::resource('almacenes',    'AlmacenController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('proveedor',    'ProveedorController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    
    Route::resource('pedidos',          'PedidoController',     ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::get('pedidos-stats',         'PedidoController@stats');
    Route::get('pedidos-presupuesto',   'PedidoController@obtenerDatosPresupuesto');
    
    Route::group(['middleware' => 'almacen'], function () {
        Route::resource('entregas',         'EntregaController',  ['only' => ['index', 'show', 'store','update','destroy']]);
        Route::get('entregas-stats',        'EntregaController@stats'); 
        Route::resource('movimientos',    'MovimientoController',    ['only' => ['index', 'show', 'store','update','destroy']]);

        //Ruta para listado de medicamentos a travez de un autocomplete, soporta paginación y busqueda
       Route::resource('catalogo-insumos',  'CatalogoInsumoController',     ['only' => ['index', 'show']]);
    
    
    });

    Route::resource('stock',    'StockController',    ['only' => ['index']]);
    Route::resource('comprobar-stock',    'ComprobarStockController',    ['only' => ['index']]);

    Route::resource('clues-servicio',    'CluesServicioController',    ['only' => ['index', 'show', 'store','update','destroy']]);

    //catalogos checherman
    Route::resource('unidad-medida',    'UnidadMedidaController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('unidades-medicas',    'UnidadesMedicasController',    ['only' => ['index', 'show', 'store','update','destroy']]);	
    Route::resource('via-administracion',    'ViaAdministracionController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('tipo-pedido',    'TipoPedidoController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('tipo-movimiento','TipoMovimientoController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('tipo-insumo','TipoInsumoController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('servidor','ServidorController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('marcas','MarcaController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('servicios','ServicioController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('grupos-insumos','GrupoInsumoController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('presentaciones-medicamentos','PresentacionMedicamentoController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('turnos','TurnoController',    ['only' => ['index', 'show', 'store','update','destroy']]);

    // catalogos checherman



    Route::resource('recepcion-pedido', 'RecepcionPedidoController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('receta',           'RecetaController',             ['only' => ['index', 'show', 'store','update','destroy']]);

     
    Route::group(['prefix' => 'sync','namespace' => 'Sync'], function () {
        Route::get('manual',    'SincronizacionController@manual');        
        Route::get('auto',      'SincronizacionController@auto');
        Route::post('importar', 'SincronizacionController@importarSync');
        Route::post('confirmar', 'SincronizacionController@confirmarSync');
    });
    
});
