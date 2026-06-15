<?php

declare(strict_types=1);

use Medas\ObjectInstantiator\ObjectInstantiator;
use Medas\MigrationBuilder\MigrationBuilderPackage;
use Medas\ServiceManager\{ServiceConfigBuilder, ServiceManager};

chdir(__DIR__);

new ServiceManager(function (): ServiceConfig {
    $config = new ServiceConfigBuilder(ObjectInstantiator::class);

    $config->addPackages([
        MigrationBuilderPackage::instance(),
    ]);

    return $config;
});
