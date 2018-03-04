<?php

/*
 * Este archivo es parte del procedimiento de aplicación de parches al código del sistema de servidores offline
 *
 * (c) Hugo César Gutiérrez Corzo <hugo.corzo@outlook.com>
 *
 */


return [
	'cliente' => [
		[ "nombre" => "b924b4b668fc43cdcd979783c5bd2699.sial.cliente.1.patch", "fecha" => "15 febrero 2018", "ejecutar" =>  "\App\Librerias\Patches\ParcheDemo::ejecutar"],
	],
	'api' => [
		[ "nombre" => "f24dbf6be70ab8b018323d6ea4601455.sial.api.1.patch", "fecha" => "03 enero 2018", "ejecutar" =>  "\App\Librerias\Patches\ParcheDemo::ejecutar"],
	]
];