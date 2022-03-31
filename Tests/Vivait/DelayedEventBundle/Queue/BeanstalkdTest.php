<?php

declare(strict_types=1);

namespace Tests\Vivait\DelayedEventBundle\Queue;

use DateInterval;
use DateTimeImmutable;
use Pheanstalk\Contract\PheanstalkInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Factory\RandomBasedUuidFactory;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;
use Symfony\Contracts\EventDispatcher\Event;
use Vivait\DelayedEventBundle\Event\PriorityAwareEvent;
use Vivait\DelayedEventBundle\Event\SelfDelayingEvent;
use Vivait\DelayedEventBundle\Queue\Beanstalkd;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;

use function json_encode;

/**
 * Class BeanstalkdTest
 * @package Tests\Vivait\DelayedEventBundle\Queue
 */
class BeanstalkdTest extends TestCase
{
    const TEST_ENVIRONMENT = 'test';

    /**
     * @var MockObject|PheanstalkInterface
     */
    private $pheanstalk;

    /**
     * @var Beanstalkd
     */
    private $queue;

    /**
     * @var MockObject|SerializerInterface
     */
    private $serializer;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    protected function setUp(): void
    {
        $this->logger = $this
            ->getMockBuilder(LoggerInterface::class)
            ->getMock();

        $this->serializer = $this
            ->getMockBuilder(SerializerInterface::class)
            ->getMock();

        $this->pheanstalk = $this
            ->getMockBuilder(PheanstalkInterface::class)
            ->getMock();

        $randomUuidFactory = $this
            ->getMockBuilder(RandomBasedUuidFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $randomUuidFactory
            ->expects(self::once())
            ->method('create')
            ->willReturn(Uuid::fromString('149fba39-d67f-4d21-958f-64d3e2d87126'))
        ;

        $this->uuidFactory = $this
            ->getMockBuilder(UuidFactory::class)
            ->getMock();

        $this->uuidFactory
            ->expects(self::once())
            ->method('randomBased')
            ->willReturn($randomUuidFactory)
        ;


        $this->queue = new Beanstalkd($this->logger, $this->serializer, $this->uuidFactory, $this->pheanstalk);
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
            ->method('useTube')
            ->with(
                'delayed_events',
            )
        ;

        $this->pheanstalk
            ->expects(self::once())
            ->method('put')
            ->with(
                json_encode(
                    [
                        'id' => '149fba39-d67f-4d21-958f-64d3e2d87126',
                        'environment' => 'test',
                        'eventName' => $eventName,
                        'event' => $serialize,
                        'tube' => 'delayed_events',
                        'maxAttempts' => 1,
                        'currentAttempt' => 1,
                        'ttr' => 60,
                    ]
                ),
                1234,
                63526080
            )
        ;

        $this->queue->put(self::TEST_ENVIRONMENT, $eventName, $event, new DateInterval('P2Y4DT6H8M'));
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
            ->method('useTube')
            ->with(
                'delayed_events',
            )
        ;

        $this->pheanstalk
            ->expects(self::once())
            ->method('put')
            ->with(
                json_encode(
                    [
                        'id' => '149fba39-d67f-4d21-958f-64d3e2d87126',
                        'environment' => 'test',
                        'eventName' => $eventName,
                        'event' => $serialize,
                        'tube' => 'delayed_events',
                        'maxAttempts' => 1,
                        'currentAttempt' => 1,
                        'ttr' => 60,
                    ]
                ),
                PheanstalkInterface::DEFAULT_PRIORITY,
                63526080
            )
        ;

        $this->queue->put(self::TEST_ENVIRONMENT, $eventName, $event, new DateInterval('P2Y4DT6H8M'));
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
            ->method('useTube')
            ->with(
                'delayed_events',
            )
        ;

        $this->pheanstalk
            ->expects(self::once())
            ->method('put')
            ->with(
                $this->anything(),
                1234,
                500
            )
        ;

        $this->queue->put(self::TEST_ENVIRONMENT, $eventName, $event, new DateInterval('P2Y4DT6H8M'));
    }


    private function createEvent(int $priority): Event
    {
        return new class($priority) extends Event implements PriorityAwareEvent {

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
        return new class($eventDateTime) extends Event implements SelfDelayingEvent, PriorityAwareEvent {

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
