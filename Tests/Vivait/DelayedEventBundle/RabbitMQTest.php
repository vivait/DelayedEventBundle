<?php

namespace Tests\Vivait\DelayedEventBundle;

use PHPUnit_Framework_Assert;
use PHPUnit_Framework_TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tests\Vivait\DelayedEventBundle\app\AppKernel;
use Tests\Vivait\DelayedEventBundle\Mocks\TestListener;
use Vivait\DelayedEventBundle\EventDispatcher\DelayedEventDispatcher;
use Vivait\DelayedEventBundle\Queue\QueueInterface;

/**
 * Checks that an end-to-end delayed event works
 */
class RabbitMQTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ContainerBuilder
     */
    private $container;
    private $consolePath;

    /**
     * @var Application
     */
    private $application;
    private $callback = false;

    /**
     * RabbitMQTest constructor.
     * @param null $consolePath
     */
    function __construct($consolePath = null)
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
        $kernel = new AppKernel('rabbitmq', true);
        $kernel->boot();

        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);

        $this->container = $kernel->getContainer();

        TestListener::reset();
    }

    public function testListener()
    {
        $this->markTestIncomplete(
            'The rabbitMQ driver has not been implemented yet.'
        );

        $this->getDispatcher()->dispatch('test.event', new Event());

        PHPUnit_Framework_Assert::assertFalse(TestListener::$hasRan);

        $bufferedOutput = new BufferedOutput();

        $options = array('command' => 'vivait:delayed_event:worker', '-t' => 2, '--run-once' => true);
        $this->application->run(new ArrayInput($options), $bufferedOutput);

        PHPUnit_Framework_Assert::assertContains('Job finished successfully and removed', $bufferedOutput->fetch());
        PHPUnit_Framework_Assert::assertTrue(TestListener::$hasRan);
    }
}
