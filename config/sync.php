<?php

/*
 * Este archivo es parte del procedimiento de sincronizacion de servidores offline
 *
 * (c) Hugo César Gutiérrez Corzo <hugo.corzo@outlook.com>
 *
 */


return [

    /*
    |--------------------------------------------------------------------------
    | Catálogos cargados en el servidor
    |--------------------------------------------------------------------------
    |
    | Esta opcion devuelve la lista de catálogos que están implementados en
    | la versión instalada en el servidor local y sirve para que al momento de
    | sincronizar con el servidor remoto, se detecten las tablas que pueden ser r
    | comparadas con su ultima fecha de actualizacion.
    |
    */

    'catalogos' => [
        
        "permisos",
        "roles",
        //"permiso_rol", Esta tabla hay que agregarle los timestamps
        "servidores",
        "claves_basicas",
        "claves_basicas_detalles",
        "claves_basicas_unidades_medicas",        
        "presupuestos",
        "transferencias_presupuesto",
        "unidad_medica_presupuesto",
        "usuario_unidad_medica",
        "proveedores",   
        "puestos",
        "turnos"
        
    ],

    /*
    |--------------------------------------------------------------------------
    | Tablas para sincronizar con el servidor remoto
    |--------------------------------------------------------------------------
    |
    | Esta opcion devuelve la lista de tablas que están implementados en
    | la versión instalada en el servidor local y sean estas las que se utilicen
    | en el proceso de sincronización con el servidor remoto. 
    | Dichas tablas deben ser ordenadas de manera ascendente para no comprometer
    | las llaves foráneas y marque error la sincronización.
    |
    */

    'tablas' => [
        "usuarios",
        "almacenes",
        //"almacen_usuario", Esta tabla no tiene el formato offline
        "stock",
        "pedidos",
        "pedido_proveedor_insumos",
        "pedidos_insumos",
        "pedidos_insumos_clues",  
        "movimientos",
        "movimiento_detalles",
        "movimiento_pedido",
        "movimiento_metadatos",
        "movimiento_insumos",
        "personal_clues",
        "personal_clues_puesto",
        "actas",
        "pacientes",
        "pacientes_admision",
        "pacientes_area_responsable",
        "pacientes_responsable",
        "recetas",
        "receta_movimientos",
        "receta_detalles"
    ],
];