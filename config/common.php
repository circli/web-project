<?php declare(strict_types=1);

return [
    'app.web_domain' => 'http://{DOMAIN}/',
    'queue.host' => '127.0.0.1',
    'db.charset' => 'utf8',
    'db.type' => 'mysql',
    'db.dbname' => 'site',
    'db.dsn' => false,
    'http-client' => [
        'allow_redirects' => true,
        'timeout' => 5,
        'curl' => [
            \CURLOPT_CONNECTTIMEOUT => 2,
        ],
    ],
];
