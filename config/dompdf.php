<?php

return [
    'show_warnings' => false,
    'public_path' => null,
    'convert_entities' => true,
    'options' => [
        'font_dir' => storage_path('fonts'),
        'font_cache' => storage_path('fonts/cache'),
        'temp_dir' => storage_path('app/dompdf-temp'),
        'chroot' => base_path(),
        'default_font' => 'IPA Gothic',
        'isRemoteEnabled' => true,
        'isHtml5ParserEnabled' => true,
        'isPhpEnabled' => false,
    ],
];
