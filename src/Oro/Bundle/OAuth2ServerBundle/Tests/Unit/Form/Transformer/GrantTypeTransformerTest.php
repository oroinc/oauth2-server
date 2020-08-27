<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Form\Transformer;

use Oro\Bundle\OAuth2ServerBundle\Form\Transformer\GrantTypeTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class GrantTypeTransformerTest extends \PHPUnit\Framework\TestCase
{
    private $transformer;

    protected function setUp(): void
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

    public function testTransformOnNonArrayValue()
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('Expected argument of type "array" or "string", "integer" given.');

        $this->transformer->transform(1213);
    }

    public function testTransformOnMultiValuesArrayValue()
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('The value must contain not more than 1 element.');

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

    public function testTransformOnNonStringValue()
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('Expected argument of type "string" or "array", "integer" given.');

        $this->transformer->reverseTransform(1213);
    }

    public function testReverceTransformOnStringValue()
    {
        self::assertEquals(['test'], $this->transformer->reverseTransform('test'));
    }
}
