<?php
namespace Vivait\DelayedEventBundle\Serializer;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class SymfonySerializer
 * @package Vivait\DelayedEventBundle\Serializer
 */
class SymfonySerializer implements SerializerInterface
{
    private Serializer $serializer;

    public function __construct(array $normalizers = [])
    {
        $encoders = [new JsonEncoder()];
        $this->serializer = new Serializer($normalizers, $encoders);
    }

    /**
     * @param $event
     * @return mixed
     */
    public function serialize($event)
    {
        return $this->serializer->serialize($event, 'json');
    }

    /**
     * @param $serializedData
     * @return mixed
     */
    public function deserialize($serializedData)
    {
        return $this->serializer->deserialize($serializedData, Event::class, 'json');
    }
}
