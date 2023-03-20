<?php

declare(strict_types=1);

namespace Vivait\DelayedEventBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('vivait_delayed_event');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                ->arrayNode('queue_transport')
                    ->beforeNormalization()
                        ->ifString()
                        ->then(fn($v) => array('name' => $v))
                    ->end()
                    ->children()
                        ->scalarNode('name')
                            ->isRequired()
                            ->defaultValue('beanstalkd')
                        ->end()
                        ->variableNode('configuration')->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
