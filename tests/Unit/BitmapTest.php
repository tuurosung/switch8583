<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Bitmap;
use Tuurosung\switch8583\Exceptions\ParseException;
use Tuurosung\switch8583\Exceptions\ValidationException;

final class BitmapTest extends TestCase
{
    /**
     * Known-good vectors: a set of present fields and the hex bitmap it must
     * produce. Verified by hand against the bit layout (bit 1 = MSB of byte 0).
     *
     * @return iterable<string, array{list<int>, string}>
     */
    public static function bitmapVectors(): iterable
    {
        yield 'empty' => [[], '0000000000000000'];
        yield 'field 3 only'          => [[3], '2000000000000000'];
        yield 'fields 2,3,4'          => [[2, 3, 4], '7000000000000000'];
        yield 'fields 2,11,12'        => [[2, 11, 12], '4030000000000000'];
        yield 'field 64 (last primary)' => [[64], '0000000000000001'];
        yield 'secondary: 2 and 70'   => [[2, 70], 'C0000000000000000400000000000000'];
        yield 'secondary: field 65'   => [[65], '80000000000000008000000000000000'];
        yield 'secondary: field 128'  => [[128], '80000000000000000000000000000001'];
    }


    #[Test]
    #[DataProvider('bitmapVectors')]
    public function it_serialises_fields_to_the_expected_hex(array $fields, string $expectedHex): void
    {
        self::assertSame($expectedHex, Bitmap::fromFields(...$fields)->toHex());
    }

    #[Test]
    #[DataProvider('bitmapVectors')]
    public function it_parses_hex_back_into_the_same_fields(array $fields, string $hex): void
    {
        $bitmap = Bitmap::fromHex($hex);

        self::assertSame($fields, $bitmap->presentFields());
        self::assertSame($hex, $bitmap->toHex());
    }

    #[Test]
    #[DataProvider('bitmapVectors')]
    public function it_round_trips_through_binary(array $fields, string $hex): void
    {
        $original = Bitmap::fromFields(...$fields);
        $reparsed = Bitmap::fromBinary($original->toBinary());

        self::assertSame($original->toHex(), $reparsed->toHex());
        self::assertSame($fields, $reparsed->presentFields());
    }

    #[Test]
    public function it_normalises_unordered_and_duplicate_fields(): void
    {
        $bitmap = Bitmap::fromFields(4, 2, 3, 2, 4);

        self::assertSame([2, 3, 4], $bitmap->presentFields());
        self::assertCount(3, $bitmap);
    }

    #[Test]
    public function it_reports_field_presence(): void
    {
        $bitmap = Bitmap::fromFields(2, 4, 70);

        self::assertTrue($bitmap->has(2));
        self::assertTrue($bitmap->has(70));
        self::assertFalse($bitmap->has(3));
        self::assertFalse($bitmap->has(128));
    }

    #[Test]
    public function it_detects_whether_a_secondary_bitmap_is_required(): void
    {
        self::assertFalse(Bitmap::fromFields(2, 3, 64)->hasSecondaryBitmap());
        self::assertTrue(Bitmap::fromFields(2, 65)->hasSecondaryBitmap());
        self::assertTrue(Bitmap::fromFields(128)->hasSecondaryBitmap());
    }

    #[Test]
    public function it_accepts_lowercase_hex_and_normalises_to_uppercase(): void
    {
        $bitmap = Bitmap::fromHex('c0000000000000000400000000000000');

        self::assertSame([2, 70], $bitmap->presentFields());
        self::assertSame('C0000000000000000400000000000000', $bitmap->toHex());
    }

    #[Test]
    public function with_and_without_are_immutable(): void
    {
        $original = Bitmap::fromFields(2, 3);

        $added = $original->with(4, 70);
        $removed = $original->without(3);

        self::assertSame([2, 3], $original->presentFields(), 'original must be untouched');
        self::assertSame([2, 3, 4, 70], $added->presentFields());
        self::assertSame([2], $removed->presentFields());
    }

    #[Test]
    public function without_ignores_fields_that_are_not_present(): void
    {
        $bitmap = Bitmap::fromFields(2, 3)->without(99);

        self::assertSame([2, 3], $bitmap->presentFields());
    }

    #[Test]
    public function it_stringifies_to_hex(): void
    {
        self::assertSame('7000000000000000', (string) Bitmap::fromFields(2, 3, 4));
    }

    // --- Validation of field numbers -------------------------------------

    #[Test]
    public function it_rejects_setting_the_structural_field_one(): void
    {
        $this->expectException(ValidationException::class);

        Bitmap::fromFields(1);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function outOfRangeFields(): iterable
    {
        yield 'zero'      => [0];
        yield 'negative'  => [-1];
        yield 'too high'  => [129];
        yield 'way high'  => [200];
    }

    #[Test]
    #[DataProvider('outOfRangeFields')]
    public function it_rejects_out_of_range_fields_on_construction(int $field): void
    {
        $this->expectException(ValidationException::class);

        Bitmap::fromFields($field);
    }

    #[Test]
    #[DataProvider('outOfRangeFields')]
    public function it_rejects_out_of_range_fields_on_has(int $field): void
    {
        $this->expectException(ValidationException::class);

        Bitmap::fromFields(2)->has($field);
    }

    // --- Parsing errors ---------------------------------------------------

    #[Test]
    public function it_rejects_odd_length_hex(): void
    {
        $this->expectException(ParseException::class);

        Bitmap::fromHex('700000000000000');
    }

    #[Test]
    public function it_rejects_non_hex_characters(): void
    {
        $this->expectException(ParseException::class);

        Bitmap::fromHex('700000000000ZZ00');
    }

    #[Test]
    public function it_rejects_a_wrong_byte_count(): void
    {
        $this->expectException(ParseException::class);

        // 4 bytes — neither a primary (8) nor a dual (16) bitmap.
        Bitmap::fromHex('20000000');
    }

    #[Test]
    public function it_rejects_eight_bytes_that_claim_a_secondary_bitmap(): void
    {
        $this->expectException(ParseException::class);

        // MSB set (secondary indicated) but only 8 bytes present.
        Bitmap::fromBinary(chr(0x80) . str_repeat(chr(0x00), 7));
    }



    #[Test]
    public function it_rejects_sixteen_bytes_without_the_secondary_indicator(): void
    {
        $this->expectException(ParseException::class);

        // 16 bytes but MSB clear — the trailing 8 bytes cannot belong here.
        Bitmap::fromBinary(str_repeat(chr(0x00), 16));
    }
}