<?php
namespace Vivait\DelayedEventBundle\Normalizer;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class EventNormalizer implements NormalizerInterface
{
    /**
     * Normalizes an object into a set of arrays/scalars.
     *
     * @param object $object object to normalize
     * @param null $format format the normalization result will be encoded as
     * @param array $context Context options for the normalizer
     *
     * @return array|string|bool|int|float|null
     */
    public function normalize($object, $format = null, array $context = array())
    {
        return;
    }

    /**
     * Checks whether the given class is supported for normalization by this normalizer.
     *
     * @param mixed $data Data to normalize.
     * @param null $format The format being (de-)serialized from or into.
     *
     * @return bool
     */
    public function supportsNormalization($data, $format = null)
    {
        return (is_object($data) && $data instanceOf Event);
    }
}
