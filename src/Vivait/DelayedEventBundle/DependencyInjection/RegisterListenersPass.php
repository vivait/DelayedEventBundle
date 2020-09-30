<?php

namespace Vivait\DelayedEventBundle\DependencyInjection;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Vivait\DelayedEventBundle\Event\EventDispatcherMediator;
use Vivait\DelayedEventBundle\Registry\DelayedEventsRegistry;

/**
 * Compiler pass to register tagged services for an event dispatcher.
 */
class RegisterListenersPass implements CompilerPassInterface
{
    /**
     * @var string
     */
    protected $delayedEventsRegistry;

    /**
     * @var string
     */
    protected $listenerTag;

    /**
     * @var string
     */
    protected $subscriberTag;

    /**
     * @var string
     */
    private $delayer;

    /**
     * RegisterListenersPass constructor.
     * @param string $delayedEventsRegistry
     * @param string $delayer
     * @param string $listenerTag
     * @param string $subscriberTag
     */
    public function __construct($delayedEventsRegistry = 'vivait_delayed_event.registry', $delayer = 'vivait_delayed_event.delayer', $listenerTag = 'delayed_event.event_listener', $subscriberTag = 'delayed_event.event_subscriber')
    {
        $this->delayedEventsRegistry = $delayedEventsRegistry;
        $this->listenerTag = $listenerTag;
        $this->subscriberTag = $subscriberTag;
        $this->delayer = $delayer;
    }

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->delayedEventsRegistry) && !$container->hasAlias($this->delayedEventsRegistry)) {
            return;
        }

        $mediator = new EventDispatcherMediator(
            $container->findDefinition('event_dispatcher'),
            $container->findDefinition($this->delayedEventsRegistry),
            $this->delayer
        );

        foreach ($container->findTaggedServiceIds($this->listenerTag) as $id => $events) {
            $def = $container->getDefinition($id);
            if (!$def->isPublic()) {
                throw new InvalidArgumentException(sprintf('The service "%s" must be public as event listeners are lazy-loaded.', $id));
            }

            if ($def->isAbstract()) {
                throw new InvalidArgumentException(sprintf('The service "%s" must not be abstract as event listeners are lazy-loaded.', $id));
            }

            foreach ($events as $event) {
                $priority = $event['priority'] ?? 0;
                $delay = $event['delay'] ?? 0;

                if (!isset($event['event'])) {
                    throw new InvalidArgumentException(sprintf('Service "%s" must define the "event" attribute on "%s" tags.', $id, $this->listenerTag));
                }

                if (!isset($event['method'])) {
                    $event['method'] = 'on'.preg_replace_callback(array(
                        '/(?<=\b)[a-z]/i',
                        '/[^a-z0-9]/i',
                    ), function ($matches) { return strtoupper($matches[0]); }, $event['event']);
                    $event['method'] = preg_replace('/[^a-z0-9]/i', '', $event['method']);
                }

                $mediator->addListener($event['event'], array($id, $event['method']), $priority, $delay);
            }
        }

//        foreach ($container->findTaggedServiceIds($this->subscriberTag) as $id => $attributes) {
//            $def = $container->getDefinition($id);
//            if (!$def->isPublic()) {
//                throw new \InvalidArgumentException(sprintf('The service "%s" must be public as event subscribers are lazy-loaded.', $id));
//            }
//
//            if ($def->isAbstract()) {
//                throw new \InvalidArgumentException(sprintf('The service "%s" must not be abstract as event subscribers are lazy-loaded.', $id));
//            }
//
//            // We must assume that the class value has been correctly filled, even if the service is created by a factory
//            $class = $container->getParameterBag()->resolveValue($def->getClass());
//
//            $refClass = new \ReflectionClass($class);
//            $interface = 'Symfony\Component\EventDispatcher\EventSubscriberInterface';
//            if (!$refClass->implementsInterface($interface)) {
//                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
//            }
//
//            $definition->addMethodCall('addSubscriberService', array($id, $class));
//        }
    }
}
