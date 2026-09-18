<?php

declare(strict_types=1);

use App\Support\Config;
use App\Support\Env;
use App\Support\ErrorHandler;
use App\Support\Logger;

require_once __DIR__ . '/autoload.php';

$basePath = dirname(__DIR__);

Env::load($basePath . '/.env');
Config::load(require $basePath . '/config/app.php');

$logger = new Logger($basePath . '/storage/logs/app.log');
ErrorHandler::register($logger, (bool) Config::get('app.debug'));

date_default_timezone_set((string) Config::get('app.timezone'));

return new App\Kernel($basePath, $logger);
