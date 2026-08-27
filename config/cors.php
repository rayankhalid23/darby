<?php

return [

    'paths' => ['api/*', 'storage/*', 'sanctum/csrf-cookie', 'login', 'register'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    // إضافة الترويسة الخاصة بالتجاوز هنا
    'allowed_headers' => ['*', 'bypass-tunnel-reminder'],

    'exposed_headers' => ['bypass-tunnel-reminder'],

    'max_age' => 86400,

    'supports_credentials' => false,

];