<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

// Extra routes only: the CRUD routes come from the core `gingerminds_crud` loader.
return static function (RoutingConfigurator $routes): void {
};
