<?php

namespace Vivait\DelayedEventBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class RegisterNormalizersPass
 * @package Vivait\DelayedEventBundle\DependencyInjection
 */
class RegisterNormalizersPass implements CompilerPassInterface
{
    /**
     * RegisterNormalizersPass constructor.
     * @param string $serializerService
     * @param string $tag
     */
    public function __construct($serializerService = 'vivait_delayed_event.serializer', $tag = 'delayed_event.normalizer')
    {
        $this->serializerService = $serializerService;
        $this->tag = $tag;
    }

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->serializerService) && !$container->hasAlias($this->serializerService)) {
            return;
        }

        $definition = $container->findDefinition($this->serializerService);
        $normalizers = [];

        foreach ($container->findTaggedServiceIds($this->tag) as $id => $attributes) {
            $normalizers[$id] = new Reference($id);
        }

        $definition->replaceArgument(0, $normalizers);
    }
}
