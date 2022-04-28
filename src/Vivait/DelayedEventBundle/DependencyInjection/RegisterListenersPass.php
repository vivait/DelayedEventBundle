<?php

namespace Vivait\DelayedEventBundle\DependencyInjection;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;
use Vivait\DelayedEventBundle\Event\EventDispatcherMediator;

use function is_string;
use function preg_replace;
use function preg_replace_callback;
use function sprintf;
use function strtoupper;

/**
 * Compiler pass to register tagged services for an event dispatcher.
 */
class RegisterListenersPass implements CompilerPassInterface
{
    protected string $delayedEventsRegistry;
    protected string $listenerTag;
    protected string $subscriberTag;
    private string $delayer;

    public function __construct(
        string $delayedEventsRegistry = 'vivait_delayed_event.registry',
        string $delayer = 'vivait_delayed_event.delayer',
        string $listenerTag = 'delayed_event.event_listener',
        string $subscriberTag = 'delayed_event.event_subscriber'
    ) {
        $this->delayedEventsRegistry = $delayedEventsRegistry;
        $this->listenerTag = $listenerTag;
        $this->subscriberTag = $subscriberTag;
        $this->delayer = $delayer;
    }

    public function process(ContainerBuilder $container): void
    {
        if (
            (! $container->hasDefinition($this->delayedEventsRegistry))
            && (! $container->hasAlias($this->delayedEventsRegistry))
        ) {
            return;
        }

        $enabled = $container->getParameter('vivait_delayed_event.enabled');
        $eventDispatcherDefinition = $container->findDefinition('event_dispatcher');

        $mediator = new EventDispatcherMediator(
            $eventDispatcherDefinition,
            $container->findDefinition($this->delayedEventsRegistry),
            $this->delayer,
        );

        foreach ($container->findTaggedServiceIds($this->listenerTag) as $id => $events) {
            $def = $container->getDefinition($id);
            if (!$def->isPublic()) {
                throw new InvalidArgumentException(
                    sprintf('The service "%s" must be public as event listeners are lazy-loaded.', $id),
                );
            }

            if ($def->isAbstract()) {
                throw new InvalidArgumentException(
                    sprintf('The service "%s" must not be abstract as event listeners are lazy-loaded.', $id),
                );
            }

            foreach ($events as $event) {
                $priority = $event['priority'] ?? 0;
                $delay = $event['delay'] ?? 0;

                if (!isset($event['event'])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Service "%s" must define the "event" attribute on "%s" tags.',
                            $id,
                            $this->listenerTag,
                        ),
                    );
                }

                $eventName = $event['event'];
                if (! is_string($eventName)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Service "%s" must define a string "event" attribute on "%s" tags.',
                            $id,
                            $this->listenerTag,
                        ),
                    );
                }

                if (! isset($event['method'])) {
                    $event['method'] = 'on' . preg_replace_callback(
                        ['/(?<=\b)[a-z]/i', '/[^a-z0-9]/i'],
                        fn($matches) => strtoupper($matches[0]),
                        $event['event'],
                    );

                    $event['method'] = preg_replace('/[^a-z0-9]/i', '', $event['method']);
                }

                if (! is_string($event['method'])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Service "%s" must define a string "method" attribute on "%s" tags.',
                            $id,
                            $this->listenerTag,
                        ),
                    );
                }

                if ($enabled) {
                    $mediator->addListener(
                        $eventName,
                        $id,
                        $event['method'],
                        $priority,
                        $delay,
                    );
                }
                else {
                    $eventDispatcherDefinition->addMethodCall(
                        'addListener',
                        [
                            $eventName,
                            [new ServiceClosureArgument(new Reference($id)), $event['method']],
                            $priority,
                        ],
                    );
                }
            }
        }
    }
}
