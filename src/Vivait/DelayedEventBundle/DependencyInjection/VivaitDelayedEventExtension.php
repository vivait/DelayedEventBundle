<?php

namespace Vivait\DelayedEventBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Config\FileLocator;

class VivaitDelayedEventExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $configuration = new Configuration($container);
        $config = $this->processConfiguration($configuration, $configs);

        if (!empty($config['queue_transport'])) {
            $container->setAlias('vivait_inspector.queue', 'vivait_inspector.queue.'. $config['queue_transport']);
        }

        if (!empty($config['serializer'])) {
            $container->setAlias('vivait_inspector.serializer', 'vivait_inspector.serializer.'. $config['serializer']);
        }
    }

}
