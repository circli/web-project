<?php declare(strict_types=1);

use Atlas\Orm\Atlas;
use Atlas\Orm\Transaction\BeginOnWrite;
use Atlas\Pdo\Connection;
use Circli\Core\Config;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

return [
    Connection::class => static function (ContainerInterface $container) {
        $config = $container->get(Config::class);
        try {
            $dsn = $config->get('db.dsn');
        }
        catch (NotFoundExceptionInterface $e) {
            $dsn = false;
        }
        if (!$dsn) {
            $dsnOpts = [
                'dbname=' . $config->get('db.dbname'),
                'host=' . $config->get('db.host'),
                'charset=' . ($config->has('db.charset') ? $config->get('db.charset') : ''),
            ];
            if ($config->has('db.port')) {
                $dsnOpts[] = 'port=' . $config->get('db.port');
            }
            $type = $config->has('db.type') ? $config->get('db.type') : 'mysql';
            $dsn = $type . ':' . implode(';', $dsnOpts);
        }

        return Connection::new($dsn, $config->get('db.username'), $config->get('db.password'), [
            '1002' => "SET NAMES 'utf8mb4'",
            '12' => 0,
        ]);
    },
    PDO::class => static function (ContainerInterface $container) {
        $config = $container->get(Config::class);
        try {
            $dsn = $config->get('db.dsn');
        }
        catch (NotFoundExceptionInterface $e) {
            $dsn = false;
        }
        if (!$dsn) {
            $dsnOpts = [
                'dbname=' . $config->get('db.dbname'),
                'host=' . $config->get('db.host'),
                'charset=' . ($config->has('db.charset') ? $config->get('db.charset') : ''),
            ];
            if ($config->has('db.port')) {
                $dsnOpts[] = 'port=' . $config->get('db.port');
            }
            $type = $config->has('db.type') ? $config->get('db.type') : 'mysql';
            $dsn = $type . ':' . implode(';', $dsnOpts);
        }
        return new \PDO($dsn, $config->get('db.username'), $config->get('db.password'), [
            '1002' => "SET NAMES 'utf8mb4'",
            '12' => 0,
        ]);
    },
    Atlas::class => static function (ContainerInterface $container) {
        return Atlas::new($container->get(Connection::class), BeginOnWrite::class);
    },
];
