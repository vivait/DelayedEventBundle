<?php

namespace Vivait\DelayedEventBundle\DependencyInjection;

use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Config\FileLocator;

class VivaitDelayedEventExtension extends ConfigurableExtension
{
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($container);
    }

    public function loadInternal(array $config, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('normalizers.yml');

        $loader->load(sprintf('queue/%s.yml', $config['queue_transport']['name']));

        if (!empty($config['queue_transport']['configuration'])) {
            $container->setParameter(
                'vivait_delayed_event.queue.configuration',
                array_merge(
                    $container->getParameter('vivait_delayed_event.queue.configuration'),
                    $config['queue_transport']['configuration']
                )
            );
        }
    }

}
