<?php

namespace Vivait\DelayedEventBundle\Queue;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\GenericContent;
use Vivait\DelayedEventBundle\IntervalCalculator;
use Vivait\DelayedEventBundle\Serializer\SerializerInterface;

class RabbitMQ implements QueueInterface
{
    protected $queue_name;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var AMQPChannel
     */
    private $channel;

    /**
     * @param SerializerInterface $serializer
     * @param \Pheanstalk_PheanstalkInterface $beanstalk
     * @param string $queue_name
     */
    public function __construct(SerializerInterface $serializer, AMQPStreamConnection $connection, $queue_name = 'delayed_events')
    {
        $this->queue_name = $queue_name;
        $this->serializer = $serializer;
        $this->channel = $connection->channel();

        $this->channel->queue_declare($queue_name, false, true, false, false);
    }

    public function put($eventName, $event, \DateInterval $delay = null, $currentAttempt = 1)
    {
        $job = $this->serializer->serialize($event);

        $seconds = IntervalCalculator::convertDateIntervalToSeconds($delay);

        $message = new AMQPMessage(json_encode(
            [
                'eventName' => $eventName,
                'event' => $job,
                'delay' => $seconds
            ]
        ), ['delivery_mode' => 2]);

        $this->channel->basic_publish($message, '', $this->queue_name);
    }

    public function get()
    {
        $job = null;

        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($this->queue_name, '', false, false, false, false, function(GenericContent $message) use (&$job) {
            $deliveryTag = $message->get('delivery_tag');
            $data = json_decode($message->get('body'), true);

            // It hasn't reached the minimum delay yet, so requeue it
            if ($message->get('timestamp') + $data['delay'] > time()) {
                $this->channel->basic_nack($deliveryTag, false, true);
            }

            $job = new Job($deliveryTag, $data['eventName'], $this->serializer->deserialize($data['event']));
        });

        // Wait for it to find the next job
//        while(count($this->channel->callbacks)) {
            $this->channel->wait();
//        }

        if (!($job instanceOf Job)) {
            throw new \RuntimeException('RabbitMQ callback did not produce job');
        }

        return $job;
    }

    public function delete(Job $job)
    {
        $this->channel->basic_ack($job->getId());
    }
}
