<?php

return [
    'neofeeder' => [
        'requests_per_minute' => (int) env('NEOFEEDER_REQUESTS_PER_MINUTE', 120),
        'timeout_ms' => (int) env('NEOFEEDER_DEFAULT_TIMEOUT_MS', 30000),
    ],
];
