<?php
// ANDARMA_FASE1_PREPROD_V1
// Se carga correctamente incluso con php artisan config:cache.
return [
    'admin_token_hours' => (int) env('ANDARMA_ADMIN_TOKEN_HOURS', 12),
];
