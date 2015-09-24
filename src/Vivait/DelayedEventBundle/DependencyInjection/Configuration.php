<?php

namespace Vivait\DelayedEventBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('vivait_delayed_event');

        $rootNode
            ->children()
                ->arrayNode('queue_transport')
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function ($v) { return array('name' => $v); })
                    ->end()
//                    ->useAttributeAsKey('name')
                    ->children()
                        ->scalarNode('name')
                            ->isRequired()
                            ->defaultValue('beanstalkd')
                        ->end()
                        ->variableNode('configuration')->end()
                    ->end()
//                    ->validate()
//                    ->ifTrue(function($value) { return !$this->container->hasDefinition('vivait_inspector.queue.'. $value); })
//                        ->thenInvalid('Invalid queue transport "%s"')
//                    ->end()
                ->end()
//                ->scalarNode('serializer')
////                    ->validate()
////                    ->ifTrue(function($value) { return !$this->container->hasDefinition('vivait_inspector.serializer.'. $value); })
////                        ->thenInvalid('Invalid serializer "%s"')
////                    ->end()
//                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
