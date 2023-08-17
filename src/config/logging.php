<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [
    'dx' => [
        'driver' => 'daily',
        'level' => 'debug',
        'path' => storage_path('logs/'.php_sapi_name().'/payroll.log'),
        'days' => 45,
        'permissions' => 0777
    ],
];
