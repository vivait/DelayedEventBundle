<?php
namespace Vivait\DelayedEventBundle\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Vivait\DelayedEventBundle\Transformer\TransformerInterface;

class Serializer implements SerializerInterface
{
    /**
     * @var NormalizerInterface[]
     */
    private $transformers;

    public function __construct(array $transformers = [])
    {
        $this->transformers = $transformers;
    }

    public function serialize($event)
    {
        $reflect = new \ReflectionObject($event);
        $props = $reflect->getProperties(
            \ReflectionProperty::IS_PUBLIC |
            \ReflectionProperty::IS_PROTECTED |
            \ReflectionProperty::IS_PRIVATE
        );

        $data = [];

        foreach ($props as $property) {
            $property->setAccessible(true);
            $attribute = $property->getName();
            $value = $property->getValue($event);

            $transformers = [];

            // Apply any transformations
            foreach ($this->transformers as $transformerName => $transformer){
                if ($transformer->supports($property, $value)) {
                    $value = $transformer->transform($value);
                    $transformers[] = $transformerName;
                }
            }

            // No transformers? Don't serialize
            if (!$transformers) {
                continue;
            }

            $data[$attribute] = [
                $value,
                $transformers
            ];
        }

        return serialize([
            $reflect->getName(),
            $data
        ]);
    }

    public function deserialize($serializedData)
    {
        list($class, $data) = unserialize($serializedData);

        $reflect = new \ReflectionClass($class);
        $event = $reflect->newInstanceWithoutConstructor();

        $props = $reflect->getProperties(
            \ReflectionProperty::IS_PUBLIC |
            \ReflectionProperty::IS_PROTECTED |
            \ReflectionProperty::IS_PRIVATE
        );

        foreach ($props as $property) {
            $property->setAccessible(true);
            $attribute = $property->getName();

            // Find out the transformers
            list($value, $transformers) = $data[$attribute];

            // Apply any reverse transformations
            foreach ($transformers as $transformerName){
                if (!isset($this->transformers[$transformerName])) {
                    throw new \OutOfBoundsException(sprintf('Invalid transformer "%s" specified when unserializing', $transformerName));
                }

                $value = $this->transformers[$transformerName]->reverseTransform($value);
            }

            $property->setValue($event, $value);
        }

        return $event;
    }
}
