<?php
namespace Vivait\DelayedEventBundle\Serializer;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Normalizer\PropertyNormalizerTest;
use Vivait\DelayedEventBundle\Transformer\DoctrineORMNormalizer;
use Vivait\DelayedEventBundle\Transformer\TransformerInterface;

class SymfonySerializer implements SerializerInterface
{
    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(array $normalizers = [])
    {
        $encoders = [new JsonEncoder()];
        $this->serializer = new Serializer($normalizers, $encoders);
    }

    public function serialize($event)
    {
        return $this->serializer->serialize($event, 'json');
    }

    public function deserialize($serializedData)
    {
        return $this->serializer->deserialize($serializedData, 'Event', 'json');
    }
}
