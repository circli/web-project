<?php declare(strict_types=1);
date_default_timezone_set('UTC');

session_start();

require __DIR__ . '/../vendor/autoload.php';

use App\App;
use Circli\Core\Environment;

$app = new App(Environment::fromValue(getenv('APP_ENV') ?: Environment::DEVELOPMENT));
$app->run();
