<?php

declare(strict_types=1);

namespace App\Test\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Id;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class IdTest extends TestCase
{
    public function testGeneratesValidUuidV7(): void
    {
        $id = Id::generate();

        // UUID v7: версия '7' в 15-м символе канонической формы.
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id->value(),
        );
    }

    public function testGeneratedIdsAreSortableByTime(): void
    {
        $first = Id::generate();
        $second = Id::generate();

        // v7 монотонно возрастает: более поздний id лексикографически не меньше.
        self::assertLessThanOrEqual(0, strcmp($first->value(), $second->value()));
    }

    public function testEqualsComparesByValue(): void
    {
        $id = Id::generate();

        self::assertTrue($id->equals(new Id($id->value())));
        self::assertFalse($id->equals(Id::generate()));
    }

    public function testRejectsInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Id('not-a-uuid');
    }
}
