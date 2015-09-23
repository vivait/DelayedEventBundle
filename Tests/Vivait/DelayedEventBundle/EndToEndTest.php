<?php

namespace Tests\Vivait\DelayedEventBundle;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tests\Vivait\DelayedEventBundle\app\AppKernel;
use Vivait\DelayedEventBundle\EventDispatcher\DelayedEventDispatcher;
use Vivait\DelayedEventBundle\Queue\QueueInterface;

/**
 * Checks that an end-to-end delayed event works
 */
class EndToEndTest extends \PHPUnit_Framework_TestCase
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

    function __construct($consolePath = null)
    {
        parent::__construct();

        $this->consolePath = $consolePath ?: __DIR__ .'/app/console.php';
    }


    /**
     * @return DelayedEventDispatcher
     */
    private function getDelayedDispatcher()
    {
        return $this->container->get('delayed_event_dispatcher');
    }

    /**
     * @return EventDispatcher
     */
    private function getDispatcher()
    {
        return $this->container->get('event_dispatcher');
    }

    /**
     * @return QueueInterface
     */
    private function getQueue()
    {
        return $this->container->get('vivait_delayed_event.queue');
    }

    function setUp() {
        $kernel = new AppKernel('test', true);
        $kernel->boot();

        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);

        $this->container = $kernel->getContainer();
    }

    public function testListener()
    {
        $this->getDelayedDispatcher()->addListener('test.event', function() {
            $this->callback = true;
        }, '5 seconds');

        $this->getDispatcher()->dispatch('test.event', new Event());

        \PHPUnit_Framework_Assert::assertFalse($this->callback);

        $options = array('command' => 'vivait:delayed_event:worker', '-t' => 2, '--run-once' => true);
        $this->application->run(new \Symfony\Component\Console\Input\ArrayInput($options), new NullOutput());

        \PHPUnit_Framework_Assert::assertTrue($this->callback);
    }
}
