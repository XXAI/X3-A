<?php

/*
 * Este archivo es parte del procedimiento de sincronizacion de servidores offline
 *
 * (c) Hugo César Gutiérrez Corzo <hugo.corzo@outlook.com>
 *
 */


return [
    'api_version' => '1.0',
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
        'catalogo_area_responsable',
        'catalogo_estado_triage',
        'catalogo_grado_lesion',
        'catalogo_localidades',
        'catalogo_motivo_egreso',
        'catalogo_municipios',
        'tipos_insumos',
        'tipos_movimientos',
        'tipos_pedidos',
        'tipos_personal',
        'tipos_personal_metadatos',
        'tipos_recetas',
        'tipos_sustancias',
        'tipos_unidad',
        'unidades_medida',
        'vias_administracion',
        'medios_contacto',
        'jurisdicciones',
        'marcas',
        'turnos',
        'especialidades',
        'factores_riesgo_embarazo',
        'formas_farmaceuticas',
        'servicios',
        'genericos',
        'grupos_insumos',
        'generico_grupo_insumo', //udpated
        'presupuestos',
        'programas',
        'proveedores',
        'puestos',
        'presentaciones_medicamentos',
        'presentaciones_sustancias',
        'categorias',
        'categorias_metadatos',
        'organismos',
        'unidades_medicas',
        'areas',
        'articulos',
        'insumos_medicos',
        'material_curacion',
        'medicamentos',
        'informacion_importante_medicamentos',
        'insumo_medico_especialidad',
        'insumo_medico_servicio',
        'listas_insumos',
        'lista_insumo_detalle',
        'auxiliares_diagnostico',
        'auxiliares_diagnostico_detalles_equipos',
        'claves_basicas',
        'claves_basicas_detalles',
        'claves_basicas_unidades_medicas',
        'comunicacion_contactos',
        'contratos',
        'contrato_clues',
        'contrato_proveedor',
        'contratos_pedidos',
        'contratos_precios',
        'empresas_espejos',
        'extensiones_contratos',
        'roles',
        'permisos',
        'permiso_rol', //udpated
        'sustancias_laboratorio',
        'unidad_medica_abasto_configuracion',
        'unidad_medica_presupuesto'
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
        'usuarios',
        'personal_clues',
        'personal_clues_metadatos',
        'personal_clues_puesto',
        'almacenes',
        'almacenes_servicios',
        'almacen_tipos_movimientos',
        'pedidos',
        'pedidos_insumos',
        'actas',
        'pedido_metadatos_sincronizaciones',
        'pedido_proveedor_insumos',
        'pedidos_alternos',
        'pedidos_insumos_clues',
        'clues_claves',
        'clues_servicios',
        'clues_turnos',
        'consumos_promedios',
        'cuadros_distribucion',
        'insumos_maximos_minimos',
        'stock',
        'stock_borrador',
        'movimientos',
        'movimiento_insumos',
        'movimiento_insumos_borrador',
        'movimiento_ajustes',
        'movimiento_detalles',
        'movimiento_metadatos',
        'movimiento_pedido',
        'log_pedido_borrador',
        'log_recepcion_borrador',
        'log_transferencias_canceladas',
        'historial_movimientos_transferencias',
        'negaciones_insumos',
        'pacientes',
        'pacientes_admision',
        'pacientes_area_responsable',
        'pacientes_responsable',
        'recetas',
        'recetas_digitales',
        'receta_detalles',
        'receta_digital_detalles',
        'receta_movimientos',
        'resguardos'
    ],
];