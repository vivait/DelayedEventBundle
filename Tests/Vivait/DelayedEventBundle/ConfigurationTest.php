<?php

namespace Tests\Vivait\DelayedEventBundle;

use Matthias\SymfonyConfigTest\PhpUnit\AbstractConfigurationTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Vivait\DelayedEventBundle\DependencyInjection\Configuration;

/**
 * Class ConfigurationTest
 * @package Tests\Vivait\DelayedEventBundle
 */
class ConfigurationTest extends AbstractConfigurationTestCase
{
    /**
     * Return the instance of ConfigurationInterface that should be used by the
     * Configuration-specific assertions in this test-case
     *
     * @return Configuration
     */
    protected function getConfiguration()
    {
        $container = new ContainerBuilder();

        $container->register('vivait_delayed_event.queue.test_queue');
        $container->register('doctrine_cache.providers.test_storage_cache');

        return new Configuration($container);
    }

    public function testEmptyConfiguration()
    {
        $this->assertConfigurationIsValid(
            [
                []
            ]
        );
    }

    public function testQueueTransportShort()
    {
        $this->assertConfigurationIsValid(
            [
                [
                    'queue_transport' => 'test_queue',
                    //'storage' => 'test_storage'
                ]
            ]
        );
    }

    public function testQueueTransportLong()
    {
        $this->assertConfigurationIsValid(
            [
                [
                    'queue_transport' => [
                        'name' => 'test_queue'
                    ],
                ]
            ]
        );
    }

    public function testQueueTransportConfiguration()
    {
        $this->assertConfigurationIsValid(
            [
                [
                    'queue_transport' => [
                        'name' => 'test_queue',
                        'configuration' => [
                            'tube' => 'test'
                        ]
                    ],
                ]
            ]
        );
    }
}
