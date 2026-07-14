<?php

declare(strict_types=1);

namespace Tuurosung\switch8583\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Codec\AsciiCodec;
use Tuurosung\switch8583\Codec\BcdCodec;
use Tuurosung\switch8583\Codec\BcdPadding;
use Tuurosung\switch8583\Exceptions\ParseException;
use Tuurosung\switch8583\Exceptions\ValidationException;
use Tuurosung\switch8583\Field\LllVarField;
use Tuurosung\switch8583\Field\LlVarField;
use Tuurosung\switch8583\Field\VariableLengthField;



#[CoversClass(LlVarField::class)]
#[CoversClass(LllVarField::class)]
#[CoversClass(VariableLengthField::class)]
final class VariableLengthFieldTest extends TestCase
{
    private static function hex(string $bytes): string
    {
        return strtoupper(bin2hex($bytes));
    }

    // --- LLVAR wire-format vectors -----------------------------------------

    #[Test]
    public function llvar_ascii_prefix_ascii_data(): void
    {
        $field = new LlVarField(37, new AsciiCodec(), new AsciiCodec());

        // "16" + the track data, all ASCII.
        self::assertSame('16TERM000112345678', $field->encode('TERM000112345678'));
    }


    
    #[Test]
    public function llvar_bcd_prefix_bcd_data_pan(): void
    {
        // Field 2 the way most switches encode it: BCD prefix, BCD PAN.
        $field = new LlVarField(19, new BcdCodec(BcdPadding::Right, 0xF), new BcdCodec());

        // 16-digit PAN: prefix 0x16, then 8 data bytes.
        self::assertSame(
            '164111111111111111',
            self::hex($field->encode('4111111111111111')),
        );
    }

    #[Test]
    public function llvar_bcd_prefix_encodes_an_odd_pan_with_a_trailing_f(): void
    {
        $field = new LlVarField(19, new BcdCodec(BcdPadding::Right, 0xF), new BcdCodec());

        // 19-digit PAN: prefix 0x19, then 10 data bytes, last nibble 0xF pad.
        self::assertSame(
            '194111111111111111112F',
            self::hex($field->encode('4111111111111111112')),
        );
    }

    #[Test]
    public function llvar_bcd_prefix_ascii_data(): void
    {
        // Mixed mode: 1-byte BCD prefix framing ASCII data.
        $field = new LlVarField(37, new AsciiCodec(), new BcdCodec());

        $encoded = $field->encode('ABC123');

        self::assertSame('06', self::hex($encoded[0]));
        self::assertSame('ABC123', substr($encoded, 1));
    }
    

    // --- LLLVAR wire-format vectors ----------------------------------------


    #[Test]
    public function lllvar_ascii_prefix_zero_pads_to_three_digits(): void
    {
        $field = new LllVarField(255, new AsciiCodec(), new AsciiCodec());

        self::assertSame('016TERM000112345678', $field->encode('TERM000112345678'));
    }


    #[Test]
    public function lllvar_bcd_prefix_occupies_two_bytes(): void
    {
        $field = new LllVarField(255, new AsciiCodec(), new BcdCodec());

        $encoded = $field->encode(str_repeat('A', 120));

        // "120" left-padded to BCD "0120" -> bytes 0x01 0x20.
        self::assertSame('0120', self::hex(substr($encoded, 0, 2)));
        self::assertSame(122, strlen($encoded));
    }


    // --- Decoding ------------------------------------------------------------


    #[Test]
    public function it_decodes_from_an_offset_and_reports_total_bytes_consumed(): void
    {
        $field = new LlVarField(19, new BcdCodec(BcdPadding::Right, 0xF), new BcdCodec());
        $buffer = "\xAA\xBB" . hex2bin('164111111111111111') . "\xCC";

        $decoded = $field->decode($buffer, 2);

        self::assertSame('4111111111111111', $decoded->value);
        self::assertSame(9, $decoded->bytesConsumed); // 1 prefix + 8 data.
    }


    #[Test]
    public function it_decodes_a_zero_length_value(): void
    {
        $field = new LlVarField(37, new AsciiCodec(), new AsciiCodec());

        $decoded = $field->decode('00');

        self::assertSame('', $decoded->value);
        self::assertSame(2, $decoded->bytesConsumed);
    }


    /**
     * @return iterable<string, array{VariableLengthField, string}>
     */
    public static function roundTripCases(): iterable
    {
        yield 'LLVAR ascii/ascii' => [
            new LlVarField(37, new AsciiCodec(), new AsciiCodec()),
            'ABC=123456?',
        ];
        yield 'LLVAR bcd/bcd even PAN' => [
            new LlVarField(19, new BcdCodec(BcdPadding::Right, 0xF), new BcdCodec()),
            '4111111111111111',
        ];
        yield 'LLVAR bcd/bcd odd PAN' => [
            new LlVarField(19, new BcdCodec(BcdPadding::Right, 0xF), new BcdCodec()),
            '4111111111111111112',
        ];
        yield 'LLVAR ascii data, bcd prefix' => [
            new LlVarField(37, new AsciiCodec(), new BcdCodec()),
            'ABC123',
        ];
        yield 'LLLVAR ascii/ascii' => [
            new LllVarField(999, new AsciiCodec(), new AsciiCodec()),
            str_repeat('X', 999),
        ];
        yield 'LLLVAR bcd prefix' => [
            new LllVarField(255, new AsciiCodec(), new BcdCodec()),
            str_repeat('A', 120),
        ];
    }


    #[Test]
    #[DataProvider('roundTripCases')]
    public function it_round_trips(VariableLengthField $field, string $value): void
    {
        $decoded = $field->decode($field->encode($value));

        self::assertSame($value, $decoded->value);
        self::assertSame(strlen($field->encode($value)), $decoded->bytesConsumed);
    }


    // --- Errors ---------------------------------------------------------------


    #[Test]
    public function it_rejects_encoding_a_value_over_the_max_length(): void
    {
        $this->expectException(ValidationException::class);

        (new LlVarField(19, new BcdCodec(), new BcdCodec()))->encode(str_repeat('1', 20));
    }


    #[Test]
    public function it_rejects_a_truncated_length_prefix(): void
    {
        $this->expectException(ParseException::class);

        (new LllVarField(255, new AsciiCodec(), new AsciiCodec()))->decode('01');
    }


    #[Test]
    public function it_rejects_truncated_data(): void
    {
        $this->expectException(ParseException::class);

        // Prefix says 16 ASCII chars; only 4 follow.
        (new LlVarField(37, new AsciiCodec(), new AsciiCodec()))->decode('16ABCD');
    }


    #[Test]
    public function it_rejects_a_prefix_that_exceeds_the_field_maximum(): void
    {
        $this->expectException(ParseException::class);

        // Prefix declares 25 digits on a field capped at 19.
        (new LlVarField(19, new BcdCodec(), new BcdCodec()))
            ->decode(hex2bin('25') . str_repeat("\x11", 13));
    }


    #[Test]
    public function it_rejects_a_non_numeric_ascii_prefix(): void
    {
        $this->expectException(ParseException::class);

        (new LlVarField(37, new AsciiCodec(), new AsciiCodec()))->decode('A6ABCDEF');
    }


    #[Test]
    public function llvar_rejects_a_max_length_over_99(): void
    {
        $this->expectException(ValidationException::class);

        new LlVarField(100, new AsciiCodec(), new AsciiCodec());
    }


    #[Test]
    public function lllvar_rejects_a_max_length_over_999(): void
    {
        $this->expectException(ValidationException::class);

        new LllVarField(1000, new AsciiCodec(), new AsciiCodec());
    }


    #[Test]
    public function it_rejects_a_non_positive_max_length(): void
    {
        $this->expectException(ValidationException::class);

        new LlVarField(0, new AsciiCodec(), new AsciiCodec());
    }
}
