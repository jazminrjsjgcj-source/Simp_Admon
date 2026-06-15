<?php

/**
 * Catálogo de secciones revisables por tipo de registro.
 *
 * Cada registro firmable tiene áreas que el revisor puede marcar
 * con observaciones. Estas son las opciones que aparecen en el
 * checkbox del panel lateral en la pantalla de revisión.
 *
 * Si agregas un módulo nuevo con observaciones, agrega aquí sus
 * secciones para que aparezcan en el panel.
 */
return [

    'secciones' => [

        'tramite' => [
            'identificacion'      => 'Identificación del trámite',
            'informacion_general' => 'Información general',
            'operacion_costos'    => 'Operación y costos burocráticos',
            'requisitos'          => 'Requisitos',
            'fundamento_juridico' => 'Fundamento jurídico',
            'ficha_portal'        => 'Ficha para portal ciudadano',
            'otros'               => 'Otros',
        ],

        'agenda' => [
            'descripcion'  => 'Descripción de la acción',
            'meta'         => 'Meta y entregables',
            'fechas'       => 'Fechas de inicio y compromiso',
            'responsable'  => 'Responsable',
            'indicador'    => 'Indicador de éxito',
            'otros'        => 'Otros',
        ],

        'propuesta_regulatoria' => [
            'identificacion'   => 'Identificación de la propuesta',
            'justificacion'    => 'Justificación',
            'problematica'     => 'Problemática a resolver',
            'alternativas'     => 'Alternativas consideradas',
            'beneficios'       => 'Beneficios esperados',
            'costos'           => 'Costos burocráticos estimados',
            'sectores'         => 'Sectores impactados',
            'fundamento'       => 'Fundamento jurídico',
            'otros'            => 'Otros',
        ],

    ],

];
