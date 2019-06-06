<?php declare(strict_types=1);

use Circli\Core\Environment;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerInterface;
use Psr\Container\ContainerInterface;

return [
    LoggerInterface::class => function (ContainerInterface $container) {
        $config = $container->get(\Circli\Core\Config::class);
        $logger = new Logger('tunnel-api');
        $logger->pushHandler(
            new StreamHandler(
                $config->get('app.basePath') . '/tmp/log/' . $config->get('app.mode') . '.log',
                Environment::PRODUCTION()->is($config->get('app.mode')) ? Logger::ERROR : Logger::DEBUG
            )
        );
        $logger->pushProcessor(new UidProcessor());

        return $logger;
    },
];
