<?php declare(strict_types=1);

use function DI\create;
use function DI\decorate;
use function DI\get;
use Circli\Middlewares\ClientIp;
use Circli\Modules\Auth\Web\Middleware\AuthAwareRouterMiddleware;
use Circli\Modules\Auth\Web\Middleware\AuthMiddleware;
use Circli\WebCore\Middleware\Container as MiddlewareContainer;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use FastRoute\DataGenerator\GroupCountBased as DataGeneratorGroupCountBased;
use Middlewares\Whoops;
use Polus\Router\FastRoute\Dispatcher as FastRouteDispatcher;
use FastRoute\Dispatcher\GroupCountBased;
use Polus\MiddlewareDispatcher\DispatcherInterface as MiddlewareDispatcherInterface;
use Polus\MiddlewareDispatcher\Relay\Dispatcher as RelayDispatcher;
use Polus\Router\FastRoute\RouterCollection;
use Polus\Router\RouterCollectionInterface;
use Polus\Router\RouterDispatcherInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Zend\Diactoros\RequestFactory;
use Zend\Diactoros\ResponseFactory;

return [
    'adr.relay_resolver' => function (ContainerInterface $container) {
        return function ($middleware) use ($container) {
            if ($middleware instanceof MiddlewareInterface) {
                return $middleware;
            }

            return $container->get($middleware);
        };
    },
    ResponseFactoryInterface::class => DI\autowire(ResponseFactory::class),
    RequestFactoryInterface::class => create(RequestFactory::class),
    MiddlewareDispatcherInterface::class => create(RelayDispatcher::class)->constructor(
        get(ResponseFactoryInterface::class),
        get('adr.relay_resolver')
    ),
    RouteCollector::class => create(RouteCollector::class)->constructor(
        get(Std::class),
        get(DataGeneratorGroupCountBased::class)
    ),
    RouterCollectionInterface::class => DI\autowire(RouterCollection::class),
    RouterDispatcherInterface::class => static function (ContainerInterface $container) {
        return new FastRouteDispatcher(
            GroupCountBased::class,
            $container->get(RouteCollector::class)
        );
    },
    'middlewares' => decorate(static function ($previous) {
        if (!$previous instanceof MiddlewareContainer) {
            $previous = new MiddlewareContainer((array) $previous);
        }
        if (class_exists(Whoops::class)) {
            $previous->addPreRouter(Whoops::class);
        }
        $previous->addPreRouter(ClientIp::class);
        $previous->addPreRouter(AuthMiddleware::class);

        $previous->addPostRouter(AuthAwareRouterMiddleware::class, 2001);

        return $previous;
    }),
];