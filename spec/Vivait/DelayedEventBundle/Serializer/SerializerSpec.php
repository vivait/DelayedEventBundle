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
    function let() {
        $this->beConstructedWith([
            'basic' => new BasicTransformer()
        ]);

    }

    function it_serializes_an_event() {
        $serialized = $this->serialize(new Event);
        $deserialized = $this->deserialize($serialized);

        $serialized->shouldBeString();

        $deserialized
            ->shouldBeLike(new Event);
    }

    function it_serializes_properties() {
        $event = new Event(3, 'anothertest');

        $serialized = $this->serialize($event);
        $deserialized = $this->deserialize($serialized);

        $deserialized->getInt()->shouldBe(3);
        $deserialized->getString()->shouldBe('anothertest');
    }

    function it_runs_a_transformer_on_a_property(TransformerInterface $transformer) {
        $this->beConstructedWith([
            'all' => $transformer
        ]);

        $transformer->supports(Argument::which('getName', 'int'), 2)->willReturn(true);
        $transformer->supports(Argument::which('getName', 'string'), 'Test')->willReturn(true);

        $transformer->transform(2)->willReturn('two')->shouldBeCalled();
        $transformer->reverseTransform('two')->willReturn(2)->shouldBeCalled();

        $transformer->transform('Test')->willReturn('test')->shouldBeCalled();
        $transformer->reverseTransform('test')->willReturn('Test')->shouldBeCalled();

        $serialized = $this->serialize(new Event);
        $this->deserialize($serialized);
    }

    function it_ignores_unserializable_properties(TransformerInterface $transformer) {
        $this->beConstructedWith([
            'numbers_only' => $transformer
        ]);

        $transformer->supports(Argument::which('getName', 'int'), 2)->willReturn(true);
        $transformer->supports(Argument::which('getName', 'string'), 'Test')->willReturn(false);

        $transformer->transform(2)->willReturn('two');
        $transformer->reverseTransform('two')->willReturn(2);

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
    protected $string;
    private   $int;

    /**
     * Event constructor.
     * @param int $int
     * @param string $string
     */
    public function __construct($int = 2, $string = 'Test')
    {
        $this->int = $int;
        $this->string = $string;
    }

    /**
     * Gets int
     * @return int
     */
    public function getInt()
    {
        return $this->int;
    }

    /**
     * Gets string
     * @return string
     */
    public function getString()
    {
        return $this->string;
    }
}
