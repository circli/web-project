<?php declare(strict_types=1);

namespace App;

use Circli\WebCore\PathContainer;
use DI\ContainerBuilder;

class Container extends \Circli\WebCore\Container
{
    protected function getPathContainer(): \Circli\Contracts\PathContainer
    {
        return new PathContainer(dirname(__DIR__, 2));
    }

    protected function initDefinitions(ContainerBuilder $builder, string $defaultDefinitionPath)
    {
        parent::initDefinitions($builder, $defaultDefinitionPath);
        $builder->addDefinitions($defaultDefinitionPath . '/db.php');
    }
}
