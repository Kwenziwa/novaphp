<?php

return [
    'name' => 'NovaPHP',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
];
