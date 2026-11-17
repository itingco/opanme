<?php
return [
    // Store secrets in .env. Laravel caches this file with php artisan config:cache.
    'key' => env('OPENAI_API_KEY', ''),
    'model' => env('OPENAI_VISION_MODEL', 'gpt-4.1-mini'),
];
