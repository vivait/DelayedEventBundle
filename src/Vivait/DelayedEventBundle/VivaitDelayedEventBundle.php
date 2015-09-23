<?php

namespace Vivait\DelayedEventBundle;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Vivait\DelayedEventBundle\DependencyInjection\RegisterListenersPass;
use Vivait\DelayedEventBundle\DependencyInjection\RegisterNormalizersPass;

class VivaitDelayedEventBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterNormalizersPass);

        $container->addCompilerPass(
            new RegisterListenersPass(
                'delayed_event_dispatcher',
                'delayed_event.event_listener',
                'delayed_event.event_subscriber'
            ),
            PassConfig::TYPE_BEFORE_REMOVING
        );
    }
}
