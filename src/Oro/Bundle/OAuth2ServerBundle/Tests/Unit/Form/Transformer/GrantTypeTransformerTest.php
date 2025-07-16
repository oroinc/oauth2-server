<?php

namespace Oro\Bundle\OAuth2ServerBundle\Tests\Unit\Form\Transformer;

use Oro\Bundle\OAuth2ServerBundle\Form\Transformer\GrantTypeTransformer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class GrantTypeTransformerTest extends TestCase
{
    private $transformer;

    #[\Override]
    protected function setUp(): void
    {
        $this->transformer = new GrantTypeTransformer();
    }

    public function testTransformOnNullValue(): void
    {
        self::assertEquals('', $this->transformer->transform(null));
    }

    public function testTransformOnEmptyArrayValue(): void
    {
        self::assertEquals('', $this->transformer->transform([]));
    }

    public function testTransformOnStringValue(): void
    {
        self::assertEquals('test', $this->transformer->transform('test'));
    }

    public function testTransformOnNonArrayValue(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('Expected argument of type "array" or "string", "integer" given.');

        $this->transformer->transform(1213);
    }

    public function testTransformOnMultiValuesArrayValue(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('The value must contain not more than 1 element.');

        $this->transformer->transform(['firsst', 'second']);
    }

    public function testTransformOnArrayValue(): void
    {
        self::assertEquals('test', $this->transformer->transform(['test']));
    }

    public function testReverceTransformOnNullValue(): void
    {
        self::assertEquals([], $this->transformer->reverseTransform(null));
    }

    public function testReverceTransformOnEmptyValue(): void
    {
        self::assertEquals([], $this->transformer->reverseTransform(''));
    }

    public function testReverceTransformOnArrayValue(): void
    {
        self::assertEquals(['test'], $this->transformer->reverseTransform(['test']));
    }

    public function testTransformOnNonStringValue(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessage('Expected argument of type "string" or "array", "integer" given.');

        $this->transformer->reverseTransform(1213);
    }

    public function testReverceTransformOnStringValue(): void
    {
        self::assertEquals(['test'], $this->transformer->reverseTransform('test'));
    }
}
