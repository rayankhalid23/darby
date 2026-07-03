<?php

return [
    'mode'                  => 'utf-8',
    'format'                => 'A4',
    'author'                => 'Darby',
    'subject'               => 'Contract',
    'keywords'              => 'PDF, Laravel, Contract',
    'creator'               => 'Darby Platform',
    'display_mode'          => 'fullpage',
    'tempDir'               => base_path('../temp/'),
    'pdf_a'                 => false,
    'pdf_a_auto'            => false,
    'use_active_forms'      => false,
    
    // إعدادات حقن الخط العربي المخصص لحل مشكلة المربعات
    'font_path' => base_path('resources/fonts/'),
    'font_data' => [
        'cairo' => [
            'R'          => 'Cairo-Regular.ttf',
            'B'          => 'Cairo-Bold.ttf',
            'useOTL'     => 0xFF,               // تفعيل هندسة الحروف للغة العربية
            'useKashida' => 75,
        ]
    ]
];