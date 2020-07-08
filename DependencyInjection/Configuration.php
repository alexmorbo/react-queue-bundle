<?php

namespace Morbo\React\Queue\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('react');
        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC for symfony/config < 4.2
            $rootNode = $treeBuilder->root('react');
        }

        $rootNode
            ->children()
                ->arrayNode('adapters')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('adapter')->end()
                            ->arrayNode('configuration')->variablePrototype()->end()->end()
                            ->scalarNode('prefix')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
        return $treeBuilder;
    }
}