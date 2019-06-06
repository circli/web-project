<?php declare(strict_types=1);

$config = include 'production.php';

$config['db.host'] = '127.0.0.1';
$config['db.port'] = '9306';
$config['db.username'] = 'root';

return $config;
