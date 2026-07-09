<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LibreOffice (conversión Word → PDF)
    |--------------------------------------------------------------------------
    |
    | Ruta al ejecutable de LibreOffice (soffice). Se usa para convertir
    | archivos Word a PDF con fidelidad completa. Si está vacío, PUNTA
    | intenta autodetectar la ruta. Si LibreOffice no está instalado, se
    | usa Dompdf como fallback (calidad básica).
    |
    | Linux:   /usr/bin/soffice
    | Windows: C:\Program Files\LibreOffice\program\soffice.exe
    |
    */
    /*
    |--------------------------------------------------------------------------
    | LibreOffice (conversión Word → PDF)
    |--------------------------------------------------------------------------
    |
    | Ruta al ejecutable de LibreOffice (soffice). Se usa para convertir
    | archivos Word a PDF con fidelidad completa. Si está vacío, PUNTA
    | intenta autodetectar la ruta. Si LibreOffice no está instalado, se
    | usa Dompdf como fallback (calidad básica).
    |
    | Linux:   /usr/bin/soffice
    | Windows: C:\Program Files\LibreOffice\program\soffice.exe
    |
    */
    'libreoffice' => [
        'path' => env('LIBREOFFICE_PATH', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | PUNTA — Generación de PDF
    |--------------------------------------------------------------------------
    |
    | cache_dir:         Carpeta donde se guardan los PDFs generados por
    |                    LibreOffice. Ruta relativa a storage/app/.
    |                    Se puede sobreescribir con PDF_CACHE_DIR en .env.
    |
    | timeout_segundos:  Tiempo máximo en segundos que PUNTA espera a que
    |                    LibreOffice termine la conversión antes de asumir
    |                    que falló. Documentos grandes (~300 páginas) pueden
    |                    necesitar hasta 120 segundos en servidores lentos.
    |
    | usar_cola:         Si es true, la conversión a PDF se encola como Job
    |                    (requiere QUEUE_CONNECTION != sync). Si es false,
    |                    la conversión es sincrónica (el usuario espera).
    |                    Para desarrollo local, dejar en false.
    |
    */
    'punta' => [
        'pdf' => [
            'cache_dir'         => env('PDF_CACHE_DIR', 'regulaciones/pdf'),
            'timeout_segundos'  => (int) env('PDF_TIMEOUT', 90),
            'usar_cola'         => env('PDF_USAR_COLA', false),
        ],
    ],

];
