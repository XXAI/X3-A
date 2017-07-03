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
Route::get('insumos-entrada-auto',          'AutoCompleteController@insumos_entradas');


// reportes y graficas
    Route::get('grafica-entregas',         'ReportePedidoController@graficaEntregas');
    Route::get('estatus-pedidos',         'ReportePedidoController@estatusEntregaPedidos');



Route::group(['middleware' => 'jwt'], function () {

    Route::resource('usuarios', 'UsuarioController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('roles', 'RolController',           ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('permisos', 'PermisoController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    

 
    Route::resource('jurisdicciones', 'JurisdiccionesController',    ['only' => ['index']]);
    Route::resource('unidades-medicas', 'UnidadesMedicasController',    ['only' => ['index']]);
    Route::resource('proveedores', 'ProveedoresController',    ['only' => ['index']]);

 
    //Route::resource('unidades-medicas', 'UnidadesMedicasController',    ['only' => ['index']]);
    Route::resource('almacenes',    'AlmacenController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('proveedor',    'ProveedorController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    
    // Akira: Estos 3 recursos que hacen aca si estan abajo dentro del middleware almacen??? :
    // Harima: Se colaron en algun merge que hicieron, los tomo como cambios en el commit, probablemente marco conflicto y lo arreglaron dejando estas lineas, los comento, si no hay problemas los elimino en el proximo commit
    //Route::resource('pedidos',          'PedidoController',     ['only' => ['index', 'show', 'store','update','destroy']]);    
    //Route::get('pedidos-stats',         'PedidoController@stats');
    //Route::get('pedidos-presupuesto',   'PedidoController@obtenerDatosPresupuesto');

    // # SECCION: Administrador central
    Route::group(['prefix' => 'administrador-central','namespace' => 'AdministradorCentral'], function () {
        Route::get('abasto', 'AbastoController@lista');
        Route::get('abasto-excel', 'AbastoController@excel');
        
        Route::get('presupuesto-pedidos', 'PedidosController@presupuesto');
        Route::get('pedidos', 'PedidosController@lista');
        Route::get('pedidos-excel', 'PedidosController@excel');
        
        // TRANSFERENCIAS DE PRESUPUESTO
        Route::get('unidades-medicas-con-presupuesto', 'TransferenciasPresupuestosController@unidadesMedicasConPresupuesto');
        Route::get('meses-presupuesto-actual', 'TransferenciasPresupuestosController@mesesPresupuestoActual');
        Route::get('anios-presupuesto-actual', 'TransferenciasPresupuestosController@aniosPresupuestoActual');
        // Este método es para obtener la lista de meses y años del presupuesto actual anteriores al mes y año actual
        Route::get('meses-anios-presupuesto-actual-anterior-fecha-actual', 'TransferenciasPresupuestosController@mesesAnioPresupuestoActualAnteriorFechaActual');        
        Route::get('presupuesto-unidad-medica', 'TransferenciasPresupuestosController@presupuestoUnidadMedica');
        Route::get('transferencias-presupuestos', 'TransferenciasPresupuestosController@lista');
        Route::post('transferencias-presupuestos', 'TransferenciasPresupuestosController@transferir');
        Route::post('transferencias-saldos-mes-actual', 'TransferenciasPresupuestosController@transferirSaldosAlMesActual');

        // ENTREGAS DEL MES
        Route::get('meses-anios-pedidos', 'EntregasMesController@mesesAnioPresupuestos');
        Route::get('entregas-pedidos-stats-mes-anio', 'EntregasMesController@statsMesAnio');
        Route::get('entregas-pedidos-stats-diarias', 'EntregasMesController@entregasPedidosStatsDiarias');
        Route::get('pedidos-clues-mes-anio', 'EntregasMesController@pedidosAnioMesClues');

        // CUMPLIMIENTO
        Route::get('cumplimiento-stats-globales', 'CumplimientoController@statsGlobales');
        Route::get('cumplimiento-stats-proveedor/{id}', 'CumplimientoController@statsPorProveedor');

        // CLAVES BASICAS
        Route::resource('claves-basicas',    'ClavesBasicasController',    ['only' => ['index', 'show', 'store','update','destroy']]);
        Route::get('claves-basicas-clues/{id}', 'ClavesBasicasController@index');
        
    });
    // # FIN SECCION


    Route::group(['middleware' => 'proveedor'], function () {
        Route::get('presupuesto-pedidos-administrador-proveedores', 'PedidosAdministradorProveedoresController@presupuesto');
        Route::get('pedidos-administrador-proveedores', 'PedidosAdministradorProveedoresController@lista');
        Route::get('pedidos-administrador-proveedores-excel', 'PedidosAdministradorProveedoresController@excel');
        Route::get('pedidos-administrador-proveedores-pedido/{id}', 'PedidosAdministradorProveedoresController@pedido');
        Route::resource('repository',      'RepositorioController',    ['only' => ['index', 'show', 'store','update','destroy']]);  
        Route::get('repository-download/{id}',  'RepositorioController@registro_descarga'); 
        Route::get('download-file/{id}',  'RepositorioController@descargar'); 
        
    });

    Route::group(['middleware' => 'almacen'], function () {

        Route::resource('almacenes',        'AlmacenController',    ['only' => ['index']]);
        Route::resource('entregas',         'EntregaController',  ['only' => ['index', 'show', 'store','update','destroy']]);
 
        Route::resource('stock',              'StockController',    ['only' => ['index']]);
        Route::resource('comprobar-stock',    'ComprobarStockController',    ['only' => ['index']]);

        Route::get('entregas-stats',        'EntregaController@stats');
        Route::resource('movimientos',    'MovimientoController',    ['only' => ['index', 'show', 'store','update','destroy']]);
        
        //Pedidos
        Route::resource('pedidos',                      'PedidoController',     ['only' => ['index', 'show', 'store','update','destroy']]);
        Route::get('pedidos-stats',                     'PedidoController@stats');
        Route::get('pedidos-presupuesto',               'PedidoController@obtenerDatosPresupuesto');
        Route::put('cancelar-pedido-transferir/{id}',   'CancelarPedidosController@cancelarYTransferir');

        Route::resource('pedidos-jurisdiccionales',         'PedidoJurisdiccionalController',     ['only' => ['index', 'show', 'store','update','destroy']]);
        Route::get('pedidos-jurisdiccionales-stats',        'PedidoJurisdiccionalController@stats');
        Route::get('pedidos-jurisdiccionales-presupuesto',  'PedidoJurisdiccionalController@obtenerDatosPresupuesto');

        Route::resource('recepcion-pedido', 'RecepcionPedidoController',    ['only' => ['show', 'update','destroy']]);

        Route::resource('mis-servicios',    'MisServiciosController',    ['only' => ['index', 'show', 'store','update','destroy']]);
	    Route::resource('mis-turnos',    'MisTurnosController',    ['only' => ['index', 'show', 'store','update','destroy']]);
	    Route::resource('mis-claves',    'MisClavesController',    ['only' => ['index', 'show', 'store','update','destroy']]);
	
	    Route::resource('mis-almacenes',    'MiAlmacenController',    ['only' => ['index', 'show', 'store','update','destroy']]);

        //Ruta para listado de medicamentos a travez de un autocomplete, soporta paginación y busqueda
        Route::resource('catalogo-insumos',  'CatalogoInsumoController',     ['only' => ['index', 'show']]);

        


        Route::get('unidades-medicas-dependientes',   'UnidadesMedicasController@unidadesMedicasDependientes');
    });

    

    Route::get('generar-excel-pedido/{id}', 'PedidoController@generarExcel');
    Route::get('generar-excel-pedido-jurisdiccional/{id}', 'PedidoJurisdiccionalController@generarExcel');

    
    Route::get('entregas-stats',        'EntregaController@stats'); 

    Route::resource('receta',           'RecetaController',             ['only' => ['index', 'show', 'store','update','destroy']]);

 
    Route::resource('clues-servicio',    'CluesServicioController',    ['only' => ['index', 'show', 'store','update','destroy']]);

    //catalogos  
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

    // catalogos  

    // # SECCION: Administrador central
    Route::group(['prefix' => 'admision','namespace' => 'AdmisionUnidad'], function () {
        Route::resource('paciente', 'PacienteController',    ['only' => ['index', 'show', 'store','update','destroy']]);
        Route::resource('paciente-egreso', 'PacienteEgresoController',    ['only' => ['index', 'show', 'store','update','destroy']]);
        Route::resource('admision', 'AdmisionController',    ['only' => ['index', 'show', 'store','update','destroy']]);
        Route::resource('egreso', 'EgresoController',    ['only' => ['index', 'show', 'store','update','destroy']]);
        Route::resource('historial', 'HistorialController',    ['only' => ['show']]);
        
        /*Catálogos*/
        Route::resource('municipio', 'MunicipioController',    ['only' => ['index', 'show']]);
        Route::resource('localidad', 'LocalidadController',    ['only' => ['index', 'show']]);
        Route::resource('motivo-egreso', 'MotivoEgresoController',    ['only' => ['index', 'show']]);
        Route::resource('triage', 'TriageController',    ['only' => ['index', 'show']]);
        Route::resource('grado-lesion', 'GradoLesionController',    ['only' => ['index', 'show']]);
        
       
    });
    
    Route::resource('receta',           'RecetaController',             ['only' => ['index', 'show', 'store','update','destroy']]);

    Route::group(['prefix' => 'sync','namespace' => 'Sync'], function () {
        Route::get('manual',    'SincronizacionController@manual');        
        Route::get('auto',      'SincronizacionController@auto');
        Route::post('importar', 'SincronizacionController@importarSync');
        Route::post('confirmar', 'SincronizacionController@confirmarSync');
    });
    
});
