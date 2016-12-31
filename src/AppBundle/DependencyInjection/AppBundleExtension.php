<?php

namespace AppBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use \Symfony\Component\HttpKernel\DependencyInjection\Extension;

class AppBundleExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
//        $configuration = $this->getConfiguration($configs, $container);
//        $this->processConfiguration($configuration, $configs);
    }

    public function getAlias()
    {
        return 't411';
    }
}
