<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Form\Transformer;

use Oro\Bundle\OAuth2ServerBundle\Form\Transformer\GrantTypeTransformer;

class GrantTypeTransformerTest extends \PHPUnit\Framework\TestCase
{
    private $transformer;

    protected function setUp()
    {
        $this->transformer = new GrantTypeTransformer();
    }

    public function testTransformOnNullValue()
    {
        self::assertEquals('', $this->transformer->transform(null));
    }

    public function testTransformOnEmptyArrayValue()
    {
        self::assertEquals('', $this->transformer->transform([]));
    }

    public function testTransformOnStringValue()
    {
        self::assertEquals('test', $this->transformer->transform('test'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage Expected argument of type "array" or "string", "integer" given.
     */
    public function testTransformOnNonArrayValue()
    {
        $this->transformer->transform(1213);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage The value must contain not more than 1 element.
     */
    public function testTransformOnMultiValuesArrayValue()
    {
        $this->transformer->transform(['firsst', 'second']);
    }

    public function testTransformOnArrayValue()
    {
        self::assertEquals('test', $this->transformer->transform(['test']));
    }

    public function testReverceTransformOnNullValue()
    {
        self::assertEquals([], $this->transformer->reverseTransform(null));
    }

    public function testReverceTransformOnEmptyValue()
    {
        self::assertEquals([], $this->transformer->reverseTransform(''));
    }

    public function testReverceTransformOnArrayValue()
    {
        self::assertEquals(['test'], $this->transformer->reverseTransform(['test']));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     * @expectedExceptionMessage Expected argument of type "string" or "array", "integer" given.
     */
    public function testTransformOnNonStringValue()
    {
        $this->transformer->reverseTransform(1213);
    }

    public function testReverceTransformOnStringValue()
    {
        self::assertEquals(['test'], $this->transformer->reverseTransform('test'));
    }
}
