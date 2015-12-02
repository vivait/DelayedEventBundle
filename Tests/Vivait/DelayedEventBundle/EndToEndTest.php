<?php

namespace Tests\Vivait\DelayedEventBundle;

use Symfony\Bundle\FrameworkBundle\Console\Application;
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
     * @return EventDispatcher
     */
    private function getDispatcher()
    {
        return $this->container->get('event_dispatcher');
    }


    function setUp() {
        $kernel = new AppKernel('test', true);
        $kernel->boot();

        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);

        $this->container = $kernel->getContainer();

        TestListener::reset();
    }

    public function testListener()
    {
        $this->getDispatcher()->dispatch('test.event', new Event());

        \PHPUnit_Framework_Assert::assertFalse(TestListener::$hasRan);

        $bufferedOutput = new BufferedOutput();

        $options = array('command' => 'vivait:delayed_event:worker', '-t' => 2, '--run-once' => true, '-v' => true);
        $this->application->run(new \Symfony\Component\Console\Input\ArrayInput($options), $bufferedOutput);

        \PHPUnit_Framework_Assert::assertContains('Performing job', $bufferedOutput->fetch());
        \PHPUnit_Framework_Assert::assertTrue(TestListener::$hasRan);
    }
}
