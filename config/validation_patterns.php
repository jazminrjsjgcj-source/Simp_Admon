<?php

/**
 * Catálogo central de patrones de validación reutilizables.
 *
 * Se usan en dos capas:
 *   1. Frontend: como atributo `pattern=""` y `inputmode=""` en los inputs
 *   2. Backend: en las Form Requests con `regex:/.../`
 *
 * Si necesitas un patrón nuevo, agrégalo aquí. La idea es no tenerlos
 * dispersos por todo el código.
 */
return [

    'numero_entero' => [
        'regex_php'   => '/^\d+$/',
        'pattern'     => '\d+',                       // para HTML pattern=
        'inputmode'   => 'numeric',
        'placeholder' => 'Solo números enteros',
        'mensaje'     => 'Solo se permiten números enteros, sin decimales ni símbolos.',
    ],

    'numero_decimal' => [
        'regex_php'   => '/^\d+(\.\d{1,4})?$/',
        'pattern'     => '\d+(\.\d{1,4})?',
        'inputmode'   => 'decimal',
        'placeholder' => 'Número con punto decimal opcional',
        'mensaje'     => 'Solo números con punto decimal (máximo 4 decimales).',
    ],

    'solo_texto' => [
        // Letras (incluye acentos y ñ), espacios, comas, puntos
        'regex_php'   => '/^[A-Za-zÁÉÍÓÚÜáéíóúüÑñ\s,.\-]+$/u',
        'pattern'     => '[A-Za-zÁÉÍÓÚÜáéíóúüÑñ\s,.\-]+',
        'inputmode'   => 'text',
        'placeholder' => 'Solo letras y espacios',
        'mensaje'     => 'Solo se permiten letras, espacios y puntuación básica.',
    ],

    'alfanumerico' => [
        // Letras y números sin espacios ni símbolos
        'regex_php'   => '/^[A-Za-z0-9]+$/',
        'pattern'     => '[A-Za-z0-9]+',
        'inputmode'   => 'text',
        'placeholder' => 'Solo letras y números, sin espacios',
        'mensaje'     => 'Solo se permiten letras y números, sin espacios ni símbolos.',
    ],

    'codigo_ur' => [
        // Código de 14 dígitos exactos
        'regex_php'   => '/^\d{14}$/',
        'pattern'     => '\d{14}',
        'inputmode'   => 'numeric',
        'placeholder' => '14 dígitos',
        'mensaje'     => 'El código de UR debe ser exactamente 14 dígitos.',
    ],

    'rfc' => [
        // RFC mexicano: 3-4 letras + 6 dígitos fecha + 3 alfanuméricos
        'regex_php'   => '/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/',
        'pattern'     => '[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}',
        'inputmode'   => 'text',
        'placeholder' => 'RFC con homoclave',
        'mensaje'     => 'Formato de RFC inválido. Ej: GODE561231A21',
    ],

    'curp' => [
        'regex_php'   => '/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/',
        'pattern'     => '[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d',
        'inputmode'   => 'text',
        'placeholder' => 'CURP de 18 caracteres',
        'mensaje'     => 'Formato de CURP inválido.',
    ],

    'telefono' => [
        // 10 dígitos
        'regex_php'   => '/^\d{10}$/',
        'pattern'     => '\d{10}',
        'inputmode'   => 'tel',
        'placeholder' => '10 dígitos sin guiones',
        'mensaje'     => 'El teléfono debe tener exactamente 10 dígitos.',
    ],

    // Descripciones largas: sin pattern (libre)
    'descripcion_libre' => [
        'regex_php'   => null,
        'pattern'     => null,
        'inputmode'   => 'text',
        'placeholder' => 'Texto libre',
        'mensaje'     => null,
    ],

];
