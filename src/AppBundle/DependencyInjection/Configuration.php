<?php

namespace AppBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('t411');

        $rootNode
            ->children()
            ->scalarNode('username')->end()
            ->scalarNode('password')->end()
            ->scalarNode('base_url')->end()
            ->end();

        return $treeBuilder;
    }
}
