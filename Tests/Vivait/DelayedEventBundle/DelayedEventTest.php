<?php

namespace Tests\Vivait\DelayedEventBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Yaml\Yaml;
use Vivait\DelayedEventBundle\DependencyInjection\Configuration;

class DelayedEventTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Symfony\Component\EventDispatcher\Event
     */
    protected $event;
    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcher
     */
    protected $dispatcher;

    protected function setUp()
    {
        $this->event = new Event();
        $this->dispatcher = new EventDispatcher();
    }

    protected function tearDown()
    {
        $this->event = null;
        $this->dispatcher = null;
    }

    public function testDelayedEvent()
    {
        $listener1 = new TestEventListener();
        $listener2 = new TestEventListener();
        $listener3 = new TestEventListener();

        $this->dispatcher->addListener('pre.foo', array($listener1, 'preFoo'), -10);
        $this->dispatcher->addListener('pre.foo', array($listener2, 'preFoo'), 10);
        $this->dispatcher->addListener('pre.foo', array($listener3, 'preFoo'));
    }
}

class TestEventListener
{
    public $preFooInvoked = false;
    public $postFooInvoked = false;
    /* Listener methods */
    public function preFoo(Event $e)
    {
        $this->preFooInvoked = true;
    }
    public function postFoo(Event $e)
    {
        $this->postFooInvoked = true;
        $e->stopPropagation();
    }
}

class TestEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array('pre.foo' => 'preFoo', 'post.foo' => 'postFoo');
    }
}
