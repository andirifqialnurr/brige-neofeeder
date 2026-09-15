<?php

return [
    'retention_days' => (int) env('UPLOAD_RETENTION_DAYS', 0),
    'mysqldump' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql' => env('MYSQL_BINARY', 'mysql'),
    'restore' => [
        'host' => env('RESTORE_MYSQL_HOST'),
        'port' => env('RESTORE_MYSQL_PORT', 3306),
        'username' => env('RESTORE_MYSQL_USER', 'root'),
        'password' => env('RESTORE_MYSQL_PASSWORD', ''),
    ],
];
