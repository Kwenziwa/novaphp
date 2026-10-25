<?php

declare(strict_types=1);

return [
    // Enable for HTTPS deployments. Do not trust arbitrary forwarded headers.
    'secure' => filter_var(getenv('SESSION_SECURE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
];
