<?php

return [
    'providers' => [App\Providers\AppServiceProvider::class],
    'name' => 'NovaPHP',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
];
