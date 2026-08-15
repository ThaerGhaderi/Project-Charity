<?php
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'], // تفعيل السماح لكل الروابط بشكل قاطع لقفل أخطاء CORS

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false, // تحويلها إلى false لتعطيل فحص ملفات الكوكيز المتعارضة بين السيرفر واللوكال

];
