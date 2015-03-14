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
        $container->register('vivait_inspector.serializer.test_serializer');

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
                    'serializer' => 'test_serializer'
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
                    'serializer' => 'test_serializer'
                ]
            ],
            'Invalid queue transport'
        );

        $this->assertConfigurationIsInvalid(
            [
                [
                    'queue_transport' => 'test_queue',
                    'serializer' => 'wrong_serializer'
                ]
            ],
            'Invalid serializer'
        );
    }
}
