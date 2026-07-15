<?php

declare(strict_types= 1);


namespace Tuurosung\switch8583\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Codec\AsciiCodec;
use Tuurosung\switch8583\Codec\BcdCodec;
use Tuurosung\switch8583\Codec\BcdPadding;
use Tuurosung\switch8583\Definitions\FieldDefinition;
use Tuurosung\switch8583\Definitions\FieldEncoding;
use Tuurosung\switch8583\Definitions\FieldFormat;
use Tuurosung\switch8583\Exceptions\ValidationException;
use Tuurosung\switch8583\Field\FixedField;
use Tuurosung\switch8583\Field\LllVarField;
use Tuurosung\switch8583\Field\LlVarField;

#[CoversClass(FieldDefinition::class)]
#[CoversClass(FieldEncoding::class)]
#[CoversClass(FieldFormat::class)]
final class FieldDefinitionTest extends TestCase
{
    // ---------Enums -----------------------------------------------------
    #[Test]
    public function encoding_maps_to_the_right_codec(): void
    {
        self::assertInstanceOf(AsciiCodec::class, FieldEncoding::Ascii->codec());
        self::assertInstanceOf(AsciiCodec::class, FieldEncoding::Binary->codec());
        self::assertInstanceOf(BcdCodec::class, FieldEncoding::Bcd->codec());
    }


    #[Test]
    public function format_knows_its_prefix_digits(): void
    {
        self::assertSame(0, FieldFormat::Fixed->prefixDigits());
        self::assertSame(2, FieldFormat::Llvar->prefixDigits());
        self::assertSame(3, FieldFormat::Lllvar->prefixDigits());

        self::assertFalse(FieldFormat::Fixed->isVariable());
        self::assertTrue(FieldFormat::Llvar->isVariable());
        self::assertTrue(FieldFormat::Lllvar->isVariable());
    }


    // --- Named factories ------------------------------------------------------


    #[Test]
    public function fixed_definitions_carry_their_facts(): void
    {
        $def = FieldDefinition::fixed(3, 'Processing Code', 6, FieldEncoding::Bcd);

        self::assertSame(3, $def->number);
        self::assertSame('Processing Code', $def->name);
        self::assertSame(FieldFormat::Fixed, $def->format);
        self::assertSame(6, $def->length);
        self::assertSame(FieldEncoding::Bcd, $def->encoding);
    }


    #[Test]
    public function variable_definitions_default_the_prefix_encoding_to_the_data_encoding(): void
    {
        $def = FieldDefinition::llvar(35, 'Track 2 Data', 37, FieldEncoding::Ascii);

        self::assertSame(FieldEncoding::Ascii, $def->lengthEncoding);
    }


    #[Test]
    public function variable_definitions_accept_a_distinct_prefix_encoding(): void
    {
        $def = FieldDefinition::lllvar(55, 'ICC Data', 255, FieldEncoding::Binary, FieldEncoding::Bcd);

        self::assertSame(FieldEncoding::Binary, $def->encoding);
        self::assertSame(FieldEncoding::Bcd, $def->lengthEncoding);
    }


    // --- fieldType() builds the right discipline ------------------------------


    #[Test]
    public function it_builds_the_matching_field_type(): void
    {
        self::assertInstanceOf(
            FixedField::class,
            FieldDefinition::fixed(3, 'Processing Code', 6, FieldEncoding::Bcd)->fieldType(),
        );
        self::assertInstanceOf(
            LlVarField::class,
            FieldDefinition::llvar(2, 'PAN', 19, FieldEncoding::Bcd)->fieldType(),
        );
        self::assertInstanceOf(
            LllVarField::class,
            FieldDefinition::lllvar(55, 'ICC Data', 255, FieldEncoding::Binary)->fieldType(),
        );
    }


    #[Test]
    public function a_definition_built_field_type_round_trips_a_value(): void
    {
        $type = FieldDefinition::fixed(4, 'Amount, Transaction', 12, FieldEncoding::Bcd)->fieldType();

        $decoded = $type->decode($type->encode('000000012500'));

        self::assertSame('000000012500', $decoded->value);
        self::assertSame(6, $decoded->bytesConsumed);
    }


    #[Test]
    public function the_pan_padding_convention_reaches_the_wire(): void
    {
        $type = FieldDefinition::llvar(2, 'PAN', 19, FieldEncoding::Bcd)
            ->withBcdPadding(BcdPadding::Right, 0xF)
            ->fieldType();

        // 19 digits: prefix 0x19, ten data bytes ending in the F pad nibble.
        self::assertSame(
            '194111111111111111112F',
            strtoupper(bin2hex($type->encode('4111111111111111112'))),
        );
    }


    #[Test]
    public function with_bcd_padding_is_immutable(): void
    {
        $original = FieldDefinition::llvar(2, 'PAN', 19, FieldEncoding::Bcd);
        $padded = $original->withBcdPadding(BcdPadding::Right, 0xF);

        self::assertSame(BcdPadding::Left, $original->bcdPadding);
        self::assertSame(BcdPadding::Right, $padded->bcdPadding);
        self::assertSame(0xF, $padded->bcdPadNibble);
    }


    // --- Validation -------------------------------------------------------------

    #[Test]
    public function it_rejects_field_number_one(): void
    {
        $this->expectException(ValidationException::class);
        FieldDefinition::fixed(1, 'Bitmap', 8, FieldEncoding::Binary);
    }


    #[Test]
    public function it_rejects_a_field_number_above_128(): void
    {
        $this->expectException(ValidationException::class);
        FieldDefinition::fixed(129, 'Nope', 8, FieldEncoding::Ascii);
    }


    #[Test]
    public function it_rejects_an_empty_name(): void
    {
        $this->expectException(ValidationException::class);
        FieldDefinition::fixed(3, '', 6, FieldEncoding::Bcd);
    }


    #[Test]
    public function it_rejects_a_non_positive_length(): void
    {
        $this->expectException(ValidationException::class);
        FieldDefinition::fixed(3, 'Processing Code', 0, FieldEncoding::Bcd);
    }


    #[Test]
    public function it_rejects_bcd_padding_on_a_non_bcd_field(): void
    {
        $this->expectException(ValidationException::class);
        FieldDefinition::llvar(35, 'Track 2 Data', 37, FieldEncoding::Ascii)
            ->withBcdPadding(BcdPadding::Right, 0xF);
    }


    #[Test]
    public function it_rejects_an_out_of_range_pad_nibble(): void
    {
        $this->expectException(ValidationException::class);

        FieldDefinition::llvar(2, 'PAN', 19, FieldEncoding::Bcd)
            ->withBcdPadding(BcdPadding::Right, 0x10);
    }
}