<?php declare(strict_types=1);

namespace App;

use Circli\Core\Environment;

class App extends \Circli\WebCore\App
{
    public function __construct(Environment $mode)
    {
        parent::__construct($mode, Container::class, dirname(__DIR__, 2));
    }
}
