<?php

declare(strict_types=1);

namespace Tests\Vivait\DelayedEventBundle\Queue;

use DateInterval;
use DateTimeImmutable;
use Pheanstalk\PheanstalkInterface;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Event;
use Vivait\DelayedEventBundle\Event\PriorityAwareEvent;
use Vivait\DelayedEventBundle\Event\SelfDelayingEvent;
use Vivait\DelayedEventBundle\Queue\Beanstalkd;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;
use function json_encode;

/**
 * Class BeanstalkdTest
 * @package Tests\Vivait\DelayedEventBundle\Queue
 */
class BeanstalkdTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|PheanstalkInterface
     */
    private $pheanstalk;

    /**
     * @var Beanstalkd
     */
    private $queue;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|SerializerInterface
     */
    private $serializer;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|LoggerInterface
     */
    private $logger;

    protected function setUp(): void
    {
        $this->logger = $this
            ->getMockBuilder(LoggerInterface::class)
            ->getMock()
        ;

        $this->serializer = $this
            ->getMockBuilder(SerializerInterface::class)
            ->getMock()
        ;

        $this->pheanstalk = $this
            ->getMockBuilder(PheanstalkInterface::class)
            ->getMock()
        ;

        $this->queue = new Beanstalkd($this->logger, $this->serializer, $this->pheanstalk);
    }

    /**
     * @test
     */
    public function itWillSetThePriorityForTheItemInTheTube(): void
    {
        $eventName = 'eventName';
        $event = $this->createEvent(1234);

        $serialize = json_encode(['serialize']);

        $this->serializer
            ->expects(self::once())
            ->method('serialize')
            ->with($event)
            ->willReturn($serialize)
        ;

        $this->pheanstalk
            ->expects(self::once())
            ->method('putInTube')
            ->with(
                'delayed_events',
                json_encode(
                    [
                        'eventName' => $eventName,
                        'event' => $serialize,
                        'tube' => 'delayed_events',
                        'maxRetries' => 1,
                        'currentAttempt' => 1,
                    ]
                ),
                1234,
                63526080
            )
        ;

        $this->queue->put($eventName, $event, new DateInterval('P2Y4DT6H8M'));
    }

    /**
     * @test
     */
    public function itWillUseTheDefaultPriorityWhenTheEventIsNotPriorityAware(): void
    {
        $eventName = 'eventName';
        $event = new Event();

        $serialize = json_encode(['serialize']);

        $this->serializer
            ->expects(self::once())
            ->method('serialize')
            ->with($event)
            ->willReturn($serialize)
        ;

        $this->pheanstalk
            ->expects(self::once())
            ->method('putInTube')
            ->with(
                'delayed_events',
                json_encode(
                    [
                        'eventName' => $eventName,
                        'event' => $serialize,
                        'tube' => 'delayed_events',
                        'maxRetries' => 1,
                        'currentAttempt' => 1,
                    ]
                ),
                PheanstalkInterface::DEFAULT_PRIORITY,
                63526080
            )
        ;

        $this->queue->put($eventName, $event, new DateInterval('P2Y4DT6H8M'));
    }

    /**
     * @test
     */
    public function itWillSetTheDelayOfASelfDelayingEvent(): void
    {
        $eventName = 'eventName';
        $now = new DateTimeImmutable();
        $event = $this->createSelfDelayingEvent($now->add(new DateInterval('PT500S')));

        $serialize = json_encode(['serialize']);

        $this->serializer
            ->expects(self::once())
            ->method('serialize')
            ->with($event)
            ->willReturn($serialize)
        ;

        $this->pheanstalk
            ->expects(self::once())
            ->method('putInTube')
            ->with(
                'delayed_events',
                $this->anything(),
                1234,
                500
            )
        ;

        $this->queue->put($eventName, $event, new DateInterval('P2Y4DT6H8M'));
    }


    private function createEvent(int $priority): Event
    {
        return new class($priority) extends Event implements PriorityAwareEvent
        {

            /**
             * @var int
             */
            private $priority;

            public function __construct(int $priority)
            {
                $this->priority = $priority;
            }

            public function getPriority(): int
            {
                return $this->priority;
            }
        };
    }

    private function createSelfDelayingEvent(DateTimeImmutable $eventDateTime): Event
    {
        return new class($eventDateTime) extends Event implements SelfDelayingEvent, PriorityAwareEvent
        {

            /**
             * @var DateTimeImmutable
             */
            private $eventDateTime;

            public function __construct(DateTimeImmutable $eventDateTime)
            {
                $this->eventDateTime = $eventDateTime;
            }

            public function getDelayedEventDateTime(): DateTimeImmutable
            {
                return $this->eventDateTime;
            }

            public function getPriority(): int
            {
                return 1234;
            }
        };
    }
}
