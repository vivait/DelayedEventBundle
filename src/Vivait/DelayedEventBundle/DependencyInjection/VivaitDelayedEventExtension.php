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
        return new Configuration();
    }

    public function loadInternal(array $config, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        //$loader->load('storage.yml');
        $loader->load('normalizers.yml');

        if (in_array($config['queue_transport'], ['beanstalkd', 'memory'])) {
            $loader->load(sprintf('queue/%s.yml', $config['queue_transport']));
        }

//        if (!empty($config['queue_transport'])) {
//            $container->setAlias('vivait_delayed_event.queue', 'vivait_delayed_event.queue.'. $config['queue_transport']);
//        }
//
//        if (!empty($config['storage'])) {
//            $container->setAlias('vivait_delayed_event.storage', 'vivait_delayed_event.storage.'. $config['storage']);
//        }
    }

}
