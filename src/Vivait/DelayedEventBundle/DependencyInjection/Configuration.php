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
                ->scalarNode('queue_transport')
                    ->defaultValue('beanstalkd')
//                    ->validate()
//                    ->ifTrue(function($value) { return !$this->container->hasDefinition('vivait_delayed_event.queue.'. $value); })
//                        ->thenInvalid('Invalid queue transport "%s"')
//                    ->end()
                ->end()
//                ->scalarNode('storage')
//                    ->validate()
//                    ->ifTrue(function($value) { return $value == 'array'; })
//                        ->thenInvalid('Array storage provider is not supported')
//                    ->ifTrue(function($value) { return !$this->container->hasDefinition(sprintf('doctrine_cache.providers.%s_cache', $value)); })
//                        ->thenInvalid('Invalid storage "%s"')
//                    ->end()
//                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
