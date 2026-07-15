<?php

declare(strict_types=1);

namespace Tuurosung\switch8583\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Bitmap;
use Tuurosung\switch8583\Codec\BcdPadding;
use Tuurosung\switch8583\Definitions\AbstractVersion;
use Tuurosung\switch8583\Definitions\FieldDefinition;
use Tuurosung\switch8583\Definitions\FieldEncoding;
use Tuurosung\switch8583\Definitions\FieldFormat;
use Tuurosung\switch8583\Definitions\Iso8583v1987;
use Tuurosung\switch8583\Definitions\Iso8583v1993;
use Tuurosung\switch8583\Definitions\VersionInterface;
use Tuurosung\switch8583\Exceptions\ValidationException;

#[CoversClass(AbstractVersion::class)]
#[CoversClass(Iso8583v1987::class)]
#[CoversClass(Iso8583v1993::class)]
final class VersionCatalogueTest extends TestCase
{
    /**
     * @return iterable<string, array{VersionInterface}>
     */
    public static function versions(): iterable
    {
        yield '1987' => [new Iso8583v1987()];
        yield '1993' => [new Iso8583v1993()];
    }

    #[Test]
    #[DataProvider('versions')]
    public function every_data_field_from_2_to_128_is_defined(VersionInterface $version): void
    {
        for ($number = Bitmap::MIN_FIELD; $number <= Bitmap::MAX_FIELD; $number++) {
            self::assertTrue(
                $version->hasField($number),
                sprintf('Field %d missing from ISO 8583:%s.', $number, $version->name()),
            );
        }

        self::assertCount(127, $version->fields());
    }

    #[Test]
    #[DataProvider('versions')]
    public function every_definition_can_build_its_field_type(VersionInterface $version): void
    {
        foreach ($version->fields() as $number => $definition) {
            self::assertSame($number, $definition->number);
            $definition->fieldType(); // Must not throw for any catalogue entry.
        }

        $this->addToAssertionCount(1);
    }

    #[Test]
    #[DataProvider('versions')]
    public function shared_anchor_fields_hold_in_both_versions(VersionInterface $version): void
    {
        $pan = $version->field(2);
        self::assertSame(FieldFormat::Llvar, $pan->format);
        self::assertSame(19, $pan->length);

        $amount = $version->field(4);
        self::assertSame(FieldFormat::Fixed, $amount->format);
        self::assertSame(12, $amount->length);

        $stan = $version->field(11);
        self::assertSame(6, $stan->length);

        $mac = $version->field(64);
        self::assertSame(FieldEncoding::Binary, $mac->encoding);
        self::assertSame(8, $mac->length);
    }

    // --- The 1987 <-> 1993 deltas the parser must respect ---------------------

    #[Test]
    public function field_39_grows_from_two_to_three_characters(): void
    {
        self::assertSame(2, (new Iso8583v1987())->field(39)->length);
        self::assertSame(3, (new Iso8583v1993())->field(39)->length);
    }

    #[Test]
    public function field_22_changes_shape_entirely(): void
    {
        $v87 = (new Iso8583v1987())->field(22);
        $v93 = (new Iso8583v1993())->field(22);

        self::assertSame(3, $v87->length);
        self::assertSame(12, $v93->length);
    }

    #[Test]
    public function field_43_becomes_variable_in_1993(): void
    {
        self::assertSame(FieldFormat::Fixed, (new Iso8583v1987())->field(43)->format);
        self::assertSame(FieldFormat::Llvar, (new Iso8583v1993())->field(43)->format);
    }

    #[Test]
    public function field_53_becomes_variable_binary_in_1993(): void
    {
        $v87 = (new Iso8583v1987())->field(53);
        $v93 = (new Iso8583v1993())->field(53);

        self::assertSame(FieldFormat::Fixed, $v87->format);
        self::assertSame(FieldFormat::Llvar, $v93->format);
        self::assertSame(FieldEncoding::Binary, $v93->encoding);
        self::assertSame(48, $v93->length);
    }

    #[Test]
    public function original_data_elements_move_from_f90_to_f56(): void
    {
        self::assertSame('Original Data Elements', (new Iso8583v1987())->field(90)->name);
        self::assertSame('Original Data Elements', (new Iso8583v1993())->field(56)->name);
    }

    // --- Errors -----------------------------------------------------------------

    #[Test]
    public function requesting_field_one_reports_it_as_undefined(): void
    {
        $this->expectException(ValidationException::class);

        (new Iso8583v1987())->field(1);
    }

    #[Test]
    public function requesting_a_field_above_128_reports_it_as_undefined(): void
    {
        $this->expectException(ValidationException::class);

        (new Iso8583v1993())->field(129);
    }

    // --- The override seam --------------------------------------------------------

    #[Test]
    public function with_field_overrides_a_definition_and_is_immutable(): void
    {
        $stock = new Iso8583v1987();

        $network = $stock->withField(
            FieldDefinition::llvar(2, 'Primary Account Number', 19, FieldEncoding::Bcd)
                ->withBcdPadding(BcdPadding::Right, 0xF),
        );

        self::assertSame(FieldEncoding::Ascii, $stock->field(2)->encoding);
        self::assertSame(FieldEncoding::Bcd, $network->field(2)->encoding);
        self::assertSame(BcdPadding::Right, $network->field(2)->bcdPadding);
    }

    #[Test]
    public function an_overridden_definition_drives_the_wire_format(): void
    {
        $network = (new Iso8583v1987())->withField(
            FieldDefinition::llvar(2, 'Primary Account Number', 19, FieldEncoding::Bcd)
                ->withBcdPadding(BcdPadding::Right, 0xF),
        );

        $type = $network->field(2)->fieldType();

        self::assertSame(
            '194111111111111111112F',
            strtoupper(bin2hex($type->encode('4111111111111111112'))),
        );
    }
}
