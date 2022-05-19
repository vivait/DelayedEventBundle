<?php

namespace Tests\Vivait\DelayedEventBundle;

use Pheanstalk\Pheanstalk;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tests\Vivait\DelayedEventBundle\Mocks\TestExceptionListener;
use Tests\Vivait\DelayedEventBundle\Mocks\TestListener;
use Tests\Vivait\DelayedEventBundle\src\Event\RetryEvent;
use Tests\Vivait\DelayedEventBundle\src\Kernel;
use Throwable;

use Vivait\DelayedEventBundle\Event\JobEvent;
use function substr;

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

        $this->eventDispatcher = self::$kernel->getContainer()->get('event_dispatcher');

        $this->application = new Application(self::$kernel);
        $this->application->setAutoExit(false);

        TestListener::reset();
        TestExceptionListener::reset();
    }

    public function tearDown(): void
    {
        /** @var Pheanstalk $pheanstalk */
        $pheanstalk = self::$kernel->getContainer()->get('leezy.pheanstalk');

        // clear out any lingering jobs

        $job = $pheanstalk->peekReady();
        $count = 0;
        while (($job !== null) && ($count < 1000)) {
            try {
                $pheanstalk->delete($job);
            } catch (Throwable $thrown) {
            }

            $job = $pheanstalk->peekReady();
            $count++;
        }

        $job = $pheanstalk->peekDelayed();
        $count = 0;
        while (($job !== null) && ($count < 1000)) {
            try {
                $pheanstalk->delete($job);
            } catch (Throwable $thrown) {
            }

            $job = $pheanstalk->peekDelayed();
            $count++;
        }
    }

    /**
     * @test
     */
    public function itWillCallTheCorrectListenerAfterDelay(): void
    {
        $this->eventDispatcher->dispatch('test.event', new Event());

        $options = ['command' => 'vivait:worker:run', '--queue-timeout' => 2, '--run-once' => true, '-v' => true];

        $result = $this->runCommand($options);

        self::assertSame(0, $result['code']);
        self::assertSame('.', $result['text']);
    }

    /**
     * @test
     */
    public function itWillHandleMultipleEnvironments(): void
    {
        // Create a second kernel with an alternative environment
        $kernel2 = static::createKernel([
            'environment' => 'test2'
        ]);
        $kernel2->boot();

        $eventDispatcher2 = $kernel2->getContainer()->get('event_dispatcher');

        $test_file = self::$kernel->getContainer()->getParameter('test_vivait_delayed_event.listener_file');
        $test_file2 = $kernel2->getContainer()->getParameter('test_vivait_delayed_event.listener_file');
        @unlink($test_file);
        @unlink($test_file2);

        try {
            $options = ['command' => 'vivait:worker:run', '--queue-timeout' => 2, '--run-once' => true, '-v' => true];

            $eventDispatcher2->dispatch('test.event', new Event());

            $this->runCommand($options);

            self::assertFileDoesNotExist($test_file);
            self::assertFileExists($test_file2);
        }
        finally {
            $kernel2->shutdown();
        }
    }

    /**
     * @test
     */
    public function itWillBuryJobIfItDoesNotSucceedAfterAllRetriesAreUsed(): void
    {
        $this->eventDispatcher->dispatch('test.event.exception', new RetryEvent());

        self::assertEquals(0, TestExceptionListener::$attempt);

        $options = ['command' => 'vivait:worker:run', '--queue-timeout' => 2, '--run-once' => true, '-v' => true];

        $result = $this->runCommand($options);
        self::assertSame(0, $result['code']);
        self::assertMatchesRegularExpression(
            '/has soft-failed and will be retried.+currentAttempt" => 1,"maxAttempts" => 3/',
            $result['text'],
        );
        self::assertSame('x', $this->lastCharacter($result['text']));

        // retry 1
        $result = $this->runCommand($options);
        self::assertSame(0, $result['code']);
        self::assertMatchesRegularExpression(
            '/has soft-failed and will be retried.+currentAttempt" => 2,"maxAttempts" => 3/',
            $result['text'],
        );
        self::assertSame('x', $this->lastCharacter($result['text']));

        // retry 2
        $result = $this->runCommand($options);
        self::assertSame(0, $result['code']);
        self::assertMatchesRegularExpression(
            '/has soft-failed but has reached the last attempt.+currentAttempt" => 3,"maxAttempts" => 3/',
            $result['text'],
        );
        self::assertSame('X', $this->lastCharacter($result['text']));

        // retry 3 (shouldn't pick anything up)
        $result = $this->runCommand($options);

        self::assertSame(0, $result['code']);
        self::assertEmpty($result['text']);
    }

    /**
     * @test
     */
    public function noRetriesWillOccurIfTheRetryOptionWasNotSet(): void
    {
        $this->eventDispatcher->dispatch('test.event.exception', new Event());

        $options = [
            'command' => 'vivait:worker:run',
            '--queue-timeout' => 2,
            '--run-once' => true,
            '-v' => true
        ];

        $result = $this->runCommand($options);
        self::assertSame(0, $result['code']);
        self::assertMatchesRegularExpression(
            '/has soft-failed but has reached the last attempt and will be buried/',
            $result['text'],
        );
        self::assertSame('X', $this->lastCharacter($result['text']));
    }


    /**
     * @test
     */
    public function itWillTriggerAnInternalEvent(): void
    {
        $triggered = false;
        $event = new Event();

        $this->eventDispatcher->addListener('vivait_delayed_event.post_queue', function(JobEvent $jobEvent) use (&$triggered, $event) {
            $triggered = true;

            $this->assertNotNull($jobEvent->getJob()->getId());
            $this->assertSame($event, $jobEvent->getOriginalEvent());
        });

        $this->eventDispatcher->dispatch('test.event', $event);

        $options = ['command' => 'vivait:worker:run', '--queue-timeout' => 2, '--run-once' => true, '-v' => true];
        $this->runCommand($options);

        self::assertTrue($triggered);
    }


    /**
     * @param array $options
     *
     * @return array{code: int, text: string}
     */
    private function runCommand(array $options): array
    {
        $output = new BufferedOutput();
        $input = new ArrayInput($options);

        $result = $this->application->run($input, $output);

        $outputText = $output->fetch();

        return ['code' => $result, 'text' => $outputText];
    }

    private function lastCharacter(string $input): string
    {
        return substr($input, -1);
    }
}
