<?php

declare(strict_types=1);

namespace Tuurosung\switch8583\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Codec\AsciiCodec;
use Tuurosung\switch8583\Codec\BcdCodec;
use Tuurosung\switch8583\Exceptions\ParseException;
use Tuurosung\switch8583\Exceptions\ValidationException;
use Tuurosung\switch8583\Field\FixedField;

#[CoversClass(FixedField::class)]
final class FixedFieldTest extends TestCase
{
    #[Test]
    public function it_encodes_a_bcd_processing_code(): void
    {
        // Field 3: n6, BCD -> 3 bytes.
        $field = new FixedField(6, new BcdCodec());

        self::assertSame('000000', strtoupper(bin2hex($field->encode('000000'))));
    }

    #[Test]
    public function it_encodes_an_ascii_terminal_id(): void
    {
        // Field 41: ans8, ASCII -> 8 bytes.
        $field = new FixedField(8, new AsciiCodec());

        self::assertSame('TERM0001', $field->encode('TERM0001'));
    }

    #[Test]
    public function it_decodes_from_an_offset_and_reports_bytes_consumed(): void
    {
        $field = new FixedField(6, new BcdCodec());
        $buffer = "\xFF\xFF" . hex2bin('123456') . "\xAA";

        $decoded = $field->decode($buffer, 2);

        self::assertSame('123456', $decoded->value);
        self::assertSame(3, $decoded->bytesConsumed);
    }

    #[Test]
    public function it_round_trips_a_twelve_digit_amount(): void
    {
        // Field 4: n12, BCD -> 6 bytes.
        $field = new FixedField(12, new BcdCodec());

        $decoded = $field->decode($field->encode('000000012500'));

        self::assertSame('000000012500', $decoded->value);
        self::assertSame(6, $decoded->bytesConsumed);
    }

    #[Test]
    public function it_rejects_a_value_shorter_than_the_declared_length(): void
    {
        $this->expectException(ValidationException::class);

        (new FixedField(6, new BcdCodec()))->encode('123');
    }

    #[Test]
    public function it_rejects_a_value_longer_than_the_declared_length(): void
    {
        $this->expectException(ValidationException::class);

        (new FixedField(6, new BcdCodec()))->encode('1234567');
    }

    #[Test]
    public function it_rejects_truncated_input_on_decode(): void
    {
        $this->expectException(ParseException::class);

        // n6 BCD needs 3 bytes; only 2 supplied.
        (new FixedField(6, new BcdCodec()))->decode(hex2bin('1234'));
    }

    #[Test]
    public function it_rejects_a_non_positive_length(): void
    {
        $this->expectException(ValidationException::class);

        new FixedField(0, new AsciiCodec());
    }

    #[Test]
    public function it_reports_its_max_length(): void
    {
        self::assertSame(12, (new FixedField(12, new BcdCodec()))->maxLength());
    }
}
