<?php

namespace Vivait\DelayedEventBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Config\FileLocator;

/**
 * Class VivaitDelayedEventExtension
 * @package Vivait\DelayedEventBundle\DependencyInjection
 */
class VivaitDelayedEventExtension extends ConfigurableExtension
{
    /**
     * @param array $config
     * @param ContainerBuilder $container
     * @return Configuration
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($container);
    }

    /**
     * @param array $config
     * @param ContainerBuilder $container
     */
    public function loadInternal(array $config, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('normalizers.yml');

        $loader->load(sprintf('queue/%s.yml', $config['queue_transport']['name']));

        if (!empty($config['queue_transport']['configuration']) && is_array($config['queue_transport']['configuration'])) {
            foreach ($config['queue_transport']['configuration'] as $key => $value) {
                $container->setParameter('vivait_delayed_event.queue.configuration.'. $key, $value);
            }
        }
    }

}
