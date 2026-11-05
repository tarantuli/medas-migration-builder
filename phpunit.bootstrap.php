<?php

declare(strict_types=1);

use Medas\MigrationBuilder\MigrationBuilderPackage;
use Medas\ObjectInstantiator\ObjectInstantiator;
use Medas\ServiceManager\{ServiceConfigBuilder, ServiceManager};

chdir(__DIR__);

new ServiceManager(function (): ServiceConfigBuilder {
    $config = new ServiceConfigBuilder(ObjectInstantiator::class, isDev: true);

    $config->addPackages([
        MigrationBuilderPackage::instance(),
    ]);

    return $config;
});
