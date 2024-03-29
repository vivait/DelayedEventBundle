<?php

namespace Tests\Vivait\DelayedEventBundle;

use Pheanstalk\Pheanstalk;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\Vivait\DelayedEventBundle\Mocks\TestExceptionListener;
use Tests\Vivait\DelayedEventBundle\Mocks\TestListener;
use Tests\Vivait\DelayedEventBundle\src\Event\RetryEvent;
use Tests\Vivait\DelayedEventBundle\src\Kernel;
use Throwable;

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

        $this->eventDispatcher = self::$container->get('event_dispatcher');

        $kernel = new Kernel('test', true);
        $kernel->boot();

        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);

        TestListener::reset();
        TestExceptionListener::reset();
    }

    public function tearDown(): void
    {
        /** @var Pheanstalk $pheanstalk */
        $pheanstalk = self::$container->get('leezy.pheanstalk');

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
        $this->eventDispatcher->dispatch(new Event(), 'test.event');

        $options = ['command' => 'vivait:worker:run', '--queue-timeout' => 2, '--run-once' => true, '-v' => true];

        $result = $this->runCommand($options);

        self::assertSame(0, $result['code']);
        self::assertSame('.', $result['text']);
    }

    /**
     * @test
     */
    public function itWillBuryJobIfItDoesNotSucceedAfterAllRetriesAreUsed(): void
    {
        $this->eventDispatcher->dispatch(new RetryEvent(), 'test.event.exception');

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
        $this->eventDispatcher->dispatch(new Event(), 'test.event.exception');

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
