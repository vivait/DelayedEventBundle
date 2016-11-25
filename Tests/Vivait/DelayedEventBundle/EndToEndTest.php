<?php

namespace Tests\Vivait\DelayedEventBundle;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tests\Vivait\DelayedEventBundle\app\AppKernel;
use Tests\Vivait\DelayedEventBundle\Mocks\TestExceptionListener;
use Tests\Vivait\DelayedEventBundle\Mocks\TestListener;
use Vivait\DelayedEventBundle\EventDispatcher\DelayedEventDispatcher;

/**
 * Checks that an end-to-end delayed event works
 */
class EndToEndTest extends \PHPUnit_Framework_TestCase
{
    
    /**
     * @var ContainerBuilder
     */
    private $container;

    /**
     * @var string
     */
    private $consolePath;

    /**
     * @var Application
     */
    private $application;

    /**
     * @param null|string $consolePath
     */
    public function __construct($consolePath = null)
    {
        parent::__construct();

        $this->consolePath = $consolePath ?: __DIR__ .'/app/console.php';
    }

    /**
     * @return EventDispatcher
     */
    private function getDispatcher()
    {
        return $this->container->get('event_dispatcher');
    }

    public function setUp() {
        $kernel = new AppKernel('test', true);
        $kernel->boot();

        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);

        $this->container = $kernel->getContainer();

        TestListener::reset();
        TestExceptionListener::reset();
    }

    public function testListener()
    {
        $this->getDispatcher()->dispatch('test.event', new Event());

        \PHPUnit_Framework_Assert::assertFalse(TestListener::$hasRan);

        $bufferedOutput = new BufferedOutput();

        $options = array('command' => 'vivait:delayed_event:worker', '-t' => 2, '--run-once' => true, '-v' => true);
        $this->application->run(new ArrayInput($options), $bufferedOutput);

        \PHPUnit_Framework_Assert::assertContains('Performing job', $bufferedOutput->fetch());
        \PHPUnit_Framework_Assert::assertTrue(TestListener::$hasRan);
    }

    public function testThatAJobWillBeBuriedIfItDoesNotSucceedAfterAllRetriesAreUsed()
    {
        $this->getDispatcher()->dispatch('test.event.exception', new Event());

        \PHPUnit_Framework_Assert::assertEquals(0, TestExceptionListener::$attempt);

        $bufferedOutput = new BufferedOutput();

        $options = [
            'command' => 'vivait:delayed_event:worker',
            '-t' => 2,
            '--run-once' => true,
            '-v' => true,
            '-r' => 2 // Retry 2 times after the first failure (so it will have 3 attempts total)
        ];

        $this->application->run(new ArrayInput($options), $bufferedOutput);
        
        $output = $bufferedOutput->fetch();
        
        \PHPUnit_Framework_Assert::assertContains(
            'Failed to perform event, attempt number 0 with exception: ',
            $output
        );
        \PHPUnit_Framework_Assert::assertContains(
            'Failed to perform event, attempt number 1 with exception: ',
            $output
        );
        \PHPUnit_Framework_Assert::assertContains(
            'Failed to perform event, attempt number 2 with exception: ',
            $output
        );
        \PHPUnit_Framework_Assert::assertContains('Burying job', $output);
    }

    public function testThatAJobWillSucceedCorrectlyDuringRetries()
    {
        $this->getDispatcher()->dispatch('test.event.exception', new Event());

        \PHPUnit_Framework_Assert::assertEquals(0, TestExceptionListener::$attempt);

        $bufferedOutput = new BufferedOutput();

        $options = [
            'command' => 'vivait:delayed_event:worker',
            '-t' => 2,
            '--run-once' => true,
            '-vvv' => true, // Very verbose mode to include `info` logs
            '-r' => 3
        ];

        $this->application->run(new ArrayInput($options), $bufferedOutput);

        $output = $bufferedOutput->fetch();
        \PHPUnit_Framework_Assert::assertContains('Job finished successfully and removed', $output);
        \PHPUnit_Framework_Assert::assertTrue(TestExceptionListener::$succeeded);
    }

    public function testThatNoRetriesWillOccurIfTheRetryOptionWasNotSet()
    {
        $this->getDispatcher()->dispatch('test.event.exception', new Event());

        \PHPUnit_Framework_Assert::assertEquals(0, TestExceptionListener::$attempt);

        $bufferedOutput = new BufferedOutput();

        $options = [
            'command' => 'vivait:delayed_event:worker',
            '-t' => 2,
            '--run-once' => true,
            '-v' => true
        ];

        $this->application->run(new ArrayInput($options), $bufferedOutput);

        $output = $bufferedOutput->fetch();

        \PHPUnit_Framework_Assert::assertContains(
            'Failed to perform event, attempt number 0 with exception: ',
            $output
        );
        \PHPUnit_Framework_Assert::assertContains('Burying job', $output);
    }
}
