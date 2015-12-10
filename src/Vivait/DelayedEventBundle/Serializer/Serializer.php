<?php
namespace Vivait\DelayedEventBundle\Serializer;

use Vivait\DelayedEventBundle\Serializer\Exception\FailedTransformationException;
use Vivait\DelayedEventBundle\Serializer\Exception\InvalidTransformerException;
use Vivait\DelayedEventBundle\Transformer\TransformerInterface;

class Serializer implements SerializerInterface
{
    /**
     * @var TransformerInterface[]
     */
    private $transformers;

    public function __construct(array $transformers = [])
    {
        $this->transformers = $transformers;
    }

    /**
     * @param object $event
     * @return string
     * @throws FailedTransformationException
     */
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
                    try {
                        $value = $transformer->transform($value);
                    }
                    catch (\Exception $exception) {
                        throw new FailedTransformationException(sprintf('Transformer "%s" failed when serializing', $transformerName), 0, $exception);
                    }

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

    /**
     * @param string $serializedData
     * @return object
     * @throws FailedTransformationException
     */
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

            // Not a serialized property
            if (!isset($data[$attribute])) {
                continue;
            }

            // Find out the transformers
            list($value, $transformers) = $data[$attribute];

            // Apply any reverse transformations
            foreach ($transformers as $transformerName){
                if (!isset($this->transformers[$transformerName])) {
                    throw new InvalidTransformerException(sprintf('Invalid transformer "%s" specified when unserializing', $transformerName));
                }

                try {
                    $value = $this->transformers[$transformerName]->reverseTransform($value);
                }
                catch (\Exception $exception) {
                    throw new FailedTransformationException(sprintf('Transformer "%s" failed when unserializing', $transformerName), 0, $exception);
                }
            }

            $property->setValue($event, $value);
        }

        return $event;
    }
}
