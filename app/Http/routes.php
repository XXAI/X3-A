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


Route::group(['middleware' => 'jwt'], function () {
    Route::resource('usuarios', 'UsuarioController',    ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('roles', 'RolController',           ['only' => ['index', 'show', 'store','update','destroy']]);
    Route::resource('permisos', 'PermisoController',    ['only' => ['index', 'show', 'store','update','destroy']]);    

    //Ruta para listado de medicamentos a travez de un autocomplete, soporta paginación y busqueda
    Route::resource('catalogo-insumos',  'CatalogoInsumoController',     ['only' => ['index', 'show']]);
    
    Route::group(['prefix' => 'sync','namespace' => 'Sync'], function () {
        Route::get('manual',    'SincronizacionController@manual');        
        Route::get('auto',      'SincronizacionController@auto');
        Route::post('importar', 'SincronizacionController@importarSync');
        Route::post('confirmar', 'SincronizacionController@confirmarSync');
    });
    
});
