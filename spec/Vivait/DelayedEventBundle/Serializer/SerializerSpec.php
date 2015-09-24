<?php

namespace spec\Vivait\DelayedEventBundle\Serializer;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Routing\Matcher\TraceableUrlMatcher;
use Vivait\DelayedEventBundle\Serializer\Serializer;
use Vivait\DelayedEventBundle\Transformer\BasicTransformer;
use Vivait\DelayedEventBundle\Transformer\TransformerInterface;

/**
 * @mixin Serializer
 */
class SerializerSpec extends ObjectBehavior
{
    function it_serializes_an_event() {
        $class = __NAMESPACE__ .'\Event';

        $serialized = $this->serialize(new Event);
        $deserialized = $this->deserialize($serialized);

        $serialized->shouldBeString();

        $deserialized
            ->shouldBeLike(new Event);
    }

    function it_runs_a_transformer_on_a_property(TransformerInterface $transformer) {
        $this->beConstructedWith([
            'basic' => $transformer
        ]);

        $transformer->supports(Argument::which('getName', 'int'), 2)->willReturn(true);
        $transformer->supports(Argument::which('getName', 'string'), 'Test')->willReturn(true);

        $transformer->transform(2)->willReturn('two')->shouldBeCalled();
        $transformer->reverseTransform('two')->willReturn('2')->shouldBeCalled();

        $transformer->transform('Test')->willReturn('test')->shouldBeCalled();
        $transformer->reverseTransform('test')->willReturn('Test')->shouldBeCalled();

        $serialized = $this->serialize(new Event);
        $this->deserialize($serialized);
    }

    function it_ignores_unserializable_properties(TransformerInterface $transformer) {
        $this->beConstructedWith([
            'basic' => $transformer
        ]);

        $transformer->supports(Argument::which('getName', 'int'), 2)->willReturn(true);
        $transformer->supports(Argument::which('getName', 'string'), 'Test')->willReturn(false);

        $transformer->transform(2)->willReturn('two');
        $transformer->reverseTransform('two')->willReturn('2');

        $transformer->transform('Test')->willReturn('test')->shouldNotBeCalled();
        $transformer->reverseTransform('test')->willReturn('Test')->shouldNotBeCalled();

        $serialized = $this->serialize(new Event);
        $this->deserialize($serialized);
    }

    public function getMatchers()
    {
        return [
            'haveKeyWithValue' => function($subject, $key, $value) {
                $property = new \ReflectionProperty(get_class($subject), $key);
                $property->setAccessible(true);

                return $property->getValue($subject) == $value;
            }
        ];
    }
}

class Event {
    private $int = 2;
    private $string = 'Test';
}
