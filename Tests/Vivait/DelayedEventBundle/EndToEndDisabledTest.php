<?php

namespace Tests\Vivait\DelayedEventBundle;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\Vivait\DelayedEventBundle\Mocks\TestExceptionListener;
use Tests\Vivait\DelayedEventBundle\Mocks\TestListener;
use Tests\Vivait\DelayedEventBundle\src\Kernel;

use function substr;

/**
 * Checks that disabling the bundle calls events instantly
 */
class EndToEndDisabledTest extends KernelTestCase
{
    private Application $application;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->eventDispatcher = self::$container->get('event_dispatcher');

        $kernel = new Kernel('test', true);
        $kernel->boot();

        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);

        TestListener::reset();
        TestExceptionListener::reset();
    }

    /**
     * @test
     */
    public function itWillCallTheCorrectListener(): void
    {
        $called = false;
        $event = new Event();
        $this->eventDispatcher->addEventListener('test.event', function($receivedEvent) use ($event, &$called) {
            $called = true;
            self::assertSame($event, $receivedEvent);
        });
        $this->eventDispatcher->dispatch($event, 'test.event');

        self::assertTrue($called);
    }
}
