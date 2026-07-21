<?php

declare (strict_types = 1);

namespace Tuurosung\switch8583\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Codec\BcdPadding;
use Tuurosung\switch8583\Definitions\FieldDefinition;
use Tuurosung\switch8583\Definitions\FieldEncoding;
use Tuurosung\switch8583\Definitions\Iso8583v1987;
use Tuurosung\switch8583\Exceptions\ParseException;
use Tuurosung\switch8583\Exceptions\ValidationException;
use Tuurosung\switch8583\Message;
use Tuurosung\switch8583\Mti;

final class MessageTest extends TestCase
{
    private function financialRequest(): Message
    {
        return Message::make('0200', new Iso8583v1987())
            ->withField(3, '000000')
            ->withField(4, '000000012500')
            ->withField(11, '000001')
            ->withField(41, 'TERM0001')
            ->withField(49, '936');
    }


    // --- Wire format ---------------------------------------------------------

    #[Test]
    public function it_serialises_mti_bitmap_then_fields_ascending(): void
    {
        // ASCII profile: every piece is literal except the bitmap.
        // Fields {3,4,11,41,49} => bytes 30 20 00 00 00 80 80 00.
        $expected = '0200'
            . hex2bin('3020000000808000')
            . '000000'
            . '000000012500'
            . '000001'
            . 'TERM0001'
            . '936';

        self::assertSame($expected, $this->financialRequest()->toBytes());
    }


    #[Test]
    public function field_insertion_order_does_not_change_the_wire(): void
    {
        $shuffled = Message::make('0200', new Iso8583v1987())
            ->withField(49, '936')
            ->withField(11, '000001')
            ->withField(4, '000000012500')
            ->withField(41, 'TERM0001')
            ->withField(3, '000000');

        self::assertSame($this->financialRequest()->toBytes(), $shuffled->toBytes());
    }


    #[Test]
    public function a_field_above_64_produces_a_sixteen_byte_bitmap(): void
    {
        $echo = Message::make('0800', new Iso8583v1987())
            ->withField(7, '0715143022')
            ->withField(11, '000001')
            ->withField(70, '301');

        self::assertTrue($echo->bitmap()->hasSecondaryBitmap());

        $expected = '0800'
            . hex2bin('82200000000000000400000000000000')
            . '0715143022'
            . '000001'
            . '301';

        self::assertSame($expected, $echo->toBytes());
    }

    #[Test]
    public function to_hex_is_the_uppercase_hex_of_to_bytes(): void
    {
        $message = $this->financialRequest();

        self::assertSame(strtoupper(bin2hex($message->toBytes())), $message->toHex());
        self::assertSame($message->toHex(), (string) $message);
    }


    // --- Round trips ------------------------------------------------------------

    #[Test]
    public function a_built_message_parses_back_identically(): void
    {
        $original = $this->financialRequest();

        $parsed = Message::parse($original->toBytes(), new Iso8583v1987());

        self::assertSame('0200', $parsed->mti()->value);
        self::assertSame($original->fields(), $parsed->fields());
        self::assertSame($original->toBytes(), $parsed->toBytes());
    }

    #[Test]
    public function a_secondary_bitmap_message_round_trips(): void
    {
        $original = Message::make('0800', new Iso8583v1987())
            ->withField(7, '0715143022')
            ->withField(11, '000001')
            ->withField(70, '301');

        $parsed = Message::parse($original->toBytes(), new Iso8583v1987());

        self::assertSame([7, 11, 70], array_keys($parsed->fields()));
        self::assertSame('301', $parsed->getField(70));
        self::assertSame($original->toBytes(), $parsed->toBytes());
    }

    #[Test]
    public function a_bcd_mti_occupies_two_bytes_and_round_trips(): void
    {
        $message = Message::make('0200', new Iso8583v1987(), FieldEncoding::Bcd)
            ->withField(3, '000000');

        $wire = $message->toBytes();

        self::assertSame('0200', strtoupper(bin2hex(substr($wire, 0, 2))));

        $parsed = Message::parse($wire, new Iso8583v1987(), FieldEncoding::Bcd);

        self::assertSame('0200', $parsed->mti()->value);
        self::assertSame('000000', $parsed->getField(3));
    }

    #[Test]
    public function a_catalogue_override_drives_the_message_wire(): void
    {
        $network = (new Iso8583v1987())->withField(
            FieldDefinition::llvar(2, 'Primary Account Number', 19, FieldEncoding::Bcd)
                ->withBcdPadding(BcdPadding::Right, 0xF),
        );

        $original = Message::make('0100', $network)
            ->withField(2, '4111111111111111112')
            ->withField(3, '000000');

        $wire = $original->toBytes();

        // MTI(4) + bitmap(8), then the BCD PAN: prefix 0x19 + 10 bytes ending in the F pad.
        self::assertSame(
            '194111111111111111112F',
            strtoupper(bin2hex(substr($wire, 12, 11))),
        );

        $parsed = Message::parse($wire, $network);

        self::assertSame('4111111111111111112', $parsed->getField(2));
    }

    // --- Builder behaviour ----------------------------------------------------------

    #[Test]
    public function with_field_is_immutable(): void
    {
        $base = Message::make('0200', new Iso8583v1987());
        $extended = $base->withField(3, '000000');

        self::assertFalse($base->hasField(3));
        self::assertTrue($extended->hasField(3));
    }

    #[Test]
    public function without_field_removes_and_tolerates_absence(): void
    {
        $message = $this->financialRequest()->withoutField(49)->withoutField(99);

        self::assertFalse($message->hasField(49));
        self::assertFalse($message->bitmap()->has(49));
    }

    #[Test]
    public function with_mti_pairs_with_response_derivation(): void
    {
        $request = $this->financialRequest();

        $reply = $request->withMti($request->mti()->response())->withField(39, '00');

        self::assertSame('0210', $reply->mti()->value);
        self::assertSame('00', $reply->getField(39));
        self::assertSame($request->getField(11), $reply->getField(11));
    }

    #[Test]
    public function it_accepts_an_mti_instance_or_string(): void
    {
        self::assertSame(
            Message::make('0100', new Iso8583v1987())->mti()->value,
            Message::make(Mti::fromString('0100'), new Iso8583v1987())->mti()->value,
        );
    }

    // --- Errors -------------------------------------------------------------------

    #[Test]
    public function with_field_rejects_a_field_the_version_does_not_define(): void
    {
        $this->expectException(ValidationException::class);

        Message::make('0200', new Iso8583v1987())->withField(1, 'x');
    }

    #[Test]
    public function get_field_rejects_an_absent_field(): void
    {
        $this->expectException(ValidationException::class);

        Message::make('0200', new Iso8583v1987())->getField(4);
    }

    #[Test]
    public function serialisation_surfaces_a_bad_value_at_the_offending_field(): void
    {
        $this->expectException(ValidationException::class);

        // F3 is fixed n6; a five-character value must fail at toBytes().
        Message::make('0200', new Iso8583v1987())->withField(3, '00000')->toBytes();
    }

    #[Test]
    public function parse_rejects_a_truncated_mti(): void
    {
        $this->expectException(ParseException::class);

        Message::parse('02', new Iso8583v1987());
    }

    #[Test]
    public function parse_rejects_a_non_numeric_mti(): void
    {
        $this->expectException(ParseException::class);

        Message::parse('ABCD' . str_repeat("\x00", 8), new Iso8583v1987());
    }

    #[Test]
    public function parse_rejects_a_truncated_bitmap(): void
    {
        $this->expectException(ParseException::class);

        Message::parse('0200' . "\x30\x20\x00", new Iso8583v1987());
    }

    #[Test]
    public function parse_rejects_a_truncated_field(): void
    {
        $this->expectException(ParseException::class);

        // Bitmap declares F3 (n6) but only three characters follow.
        Message::parse('0200' . hex2bin('2000000000000000') . '000', new Iso8583v1987());
    }

    #[Test]
    public function parse_rejects_trailing_garbage(): void
    {
        $this->expectException(ParseException::class);

        Message::parse($this->financialRequest()->toBytes() . 'X', new Iso8583v1987());
    }
}