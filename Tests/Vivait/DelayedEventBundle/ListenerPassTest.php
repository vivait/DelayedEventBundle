<?php

namespace Tests\Vivait\DelayedEventBundle;

use Matthias\SymfonyConfigTest\PhpUnit\AbstractConfigurationTestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;
use Vivait\DelayedEventBundle\DependencyInjection\Configuration;

class ConfigurationTest extends AbstractConfigurationTestCase
{
    /**
     * Return the instance of ConfigurationInterface that should be used by the
     * Configuration-specific assertions in this test-case
     *
     * @return \Symfony\Component\Config\Definition\ConfigurationInterface
     */
    protected function getConfiguration()
    {
        $container = new ContainerBuilder();

        $container->register('vivait_inspector.queue.test_queue');
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

    public function testValidDrivers()
    {
        $this->assertConfigurationIsValid(
            [
                [
                    'queue_transport' => 'test_queue',
                    'storage' => 'test_storage'
                ]
            ]
        );
    }

    public function testInvalidDrivers()
    {
        $this->assertConfigurationIsInvalid(
            [
                [
                    'queue_transport' => 'wrong_queue',
                    'storage' => 'test_storage'
                ]
            ],
            'Invalid queue transport'
        );

        $this->assertConfigurationIsInvalid(
            [
                [
                    'queue_transport' => 'test_queue',
                    'storage' => 'wrong_storage'
                ]
            ],
            'Invalid storage'
        );

    }

    public function testArrayStorageDriverIsBlocked()
    {
        // Don't accept the array storage option, since it's per-request
        $this->assertConfigurationIsInvalid(
            [
                [
                    'queue_transport' => 'test_queue',
                    'storage' => 'array'
                ]
            ],
            'Invalid storage'
        );
    }
}
