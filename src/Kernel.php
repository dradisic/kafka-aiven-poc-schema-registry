<?php

namespace Dradisic\KafkaSchema;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // No web routes needed for this library
    }
}
