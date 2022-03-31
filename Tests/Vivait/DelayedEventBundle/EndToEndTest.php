<?php

namespace Tests\Vivait\DelayedEventBundle;

use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\Vivait\DelayedEventBundle\Mocks\TestExceptionListener;
use Tests\Vivait\DelayedEventBundle\Mocks\TestListener;
use Tests\Vivait\DelayedEventBundle\src\Kernel;

/**
 * Checks that an end-to-end delayed event works
 */
class EndToEndTest extends KernelTestCase
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

    public function testListener()
    {
        $this->eventDispatcher->dispatch(new Event(), 'test.event');

        Assert::assertFalse(TestListener::$hasRan);

        $bufferedOutput = new BufferedOutput();

        $options = ['command' => 'vivait:worker:run', '--queue-timeout' => 2, '--run-once' => true, '-v' => true];
        $this->application->run(new ArrayInput($options), $bufferedOutput);

        Assert::assertStringContainsString('Performing job', $bufferedOutput->fetch());
        Assert::assertTrue(TestListener::$hasRan);
    }

    public function testThatAJobWillBeBuriedIfItDoesNotSucceedAfterAllRetriesAreUsed()
    {
        $this->eventDispatcher->dispatch(new Event(), 'test.event.exception');

        Assert::assertEquals(0, TestExceptionListener::$attempt);

        $bufferedOutput = new BufferedOutput();

        $options = [
            'command' => 'vivait:worker:run',
            '--queue-timeout' => 2,
            '--run-once' => true,
            '-v' => true,
//            '-r' => 2 // Retry 2 times after the first failure (so it will have 3 attempts total)
        ];

        $this->application->run(new ArrayInput($options), $bufferedOutput);

        $output = $bufferedOutput->fetch();

        Assert::assertStringContainsString(
            'Failed to perform event, attempt number 0 with exception: ',
            $output
        );
        Assert::assertStringContainsString(
            'Failed to perform event, attempt number 1 with exception: ',
            $output
        );
        Assert::assertStringContainsString(
            'Failed to perform event, attempt number 2 with exception: ',
            $output
        );
        Assert::assertStringContainsString('Burying job', $output);
    }

    public function testThatAJobWillSucceedCorrectlyDuringRetries()
    {
        $this->eventDispatcher->dispatch(new Event(), 'test.event.exception');

        Assert::assertEquals(0, TestExceptionListener::$attempt);

        $bufferedOutput = new BufferedOutput();

        $options = [
            'command' => 'vivait:worker:run',
            '--queue-timeout' => 2,
            '--run-once' => true,
            '-vvv' => true, // Very verbose mode to include `info` logs
//            '-r' => 3
        ];

        $this->application->run(new ArrayInput($options), $bufferedOutput);

        $output = $bufferedOutput->fetch();
        Assert::assertStringContainsString('Job finished successfully and removed', $output);
        Assert::assertTrue(TestExceptionListener::$succeeded);
    }

    public function testThatNoRetriesWillOccurIfTheRetryOptionWasNotSet()
    {
        $this->eventDispatcher->dispatch(new Event(), 'test.event.exception');

        Assert::assertEquals(0, TestExceptionListener::$attempt);

        $bufferedOutput = new BufferedOutput();

        $options = [
            'command' => 'vivait:worker:run',
            '--queue-timeout' => 2,
            '--run-once' => true,
            '-v' => true
        ];

        $this->application->run(new ArrayInput($options), $bufferedOutput);

        $output = $bufferedOutput->fetch();

        Assert::assertStringContainsString(
            'Failed to perform event, attempt number 0 with exception: ',
            $output
        );
        Assert::assertStringContainsString('Burying job', $output);
    }
}
