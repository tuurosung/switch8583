<?php

declare(strict_types=1);

namespace Tuurosung\switch8583\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Codec\BcdCodec;
use Tuurosung\switch8583\Codec\BcdPadding;
use Tuurosung\switch8583\Exceptions\EncodingException;
use Tuurosung\switch8583\Exceptions\ParseException;
use Tuurosung\switch8583\Exceptions\ValidationException;

#[CoversClass(BcdCodec::class)]
final class BcdCodecTest extends TestCase
{
    private static function hex(string $bytes): string
    {
        return strtoupper(bin2hex($bytes));
    }


    private function bcdCodec()
    {
        return new BcdCodec();
    }

    //  --------Encoding--------------------------------------------

    #[Test]
    public function it_packs_an_even_length_value(): void
    {
        self::assertSame('1234', self::hex($this->bcdCodec()->encode('1234')));
    }


    #[Test]
    public function it_packs_a_12_digit_amount(): void
    {
        self::assertSame('000000012500', self::hex($this->bcdCodec()->encode('000000012500')));
    }


    #[Test]
    public function it_left_pads_an_odd_value_by_default(): void
    {
        // Right-justified: "123" -> 0x0123
        self::assertSame('0123', self::hex($this->bcdCodec()->encode('123')));
    }


    #[Test]
    public function it_right_pads_an_odd_value_with_zero(): void
    {
        // Left-justified: "123" -> 0x1230
        $codec = new BcdCodec(BcdPadding::Right);

        self::assertSame('1230', self::hex($codec->encode('123')));
    }


    #[Test]
    public function it_right_pads_an_odd_value_with_an_f_nibble(): void
    {
        // Left-justified with 0xF pad, as some networks encode the PAN: "123" -> 0x123F
        $codec = new BcdCodec(BcdPadding::Right, 0xF);

        self::assertSame('123F', self::hex($codec->encode('123')));
    }


    #[Test]
    public function it_encodes_an_empty_value_to_empty_bytes(): void
    {
        self::assertSame('', self::hex($this->bcdCodec()->encode('')));
    }


    #[Test]
    public function it_rejects_non_decimal_input_on_encode(): void 
    {
        $this->expectException(EncodingException::class);
        
        $this->bcdCodec()->encode('12A4');
    }


    #[Test]
    public function it_rejects_white_spaces_on_encode():void
    {
        $this->expectException(EncodingException::class);

        $this->bcdCodec()->encode('12 4');
    }


    //  --------Decoding--------------------------------------------

    #[Test]
    public function it_unpacks_an_even_length_value(): void
    {
        self::assertSame('1234', $this->bcdCodec()->decode(hex2bin('1234'), 4));
    }


    #[Test]
    public function it_strips_a_left_pad_when_decoding_an_odd_value(): void
    {
        self::assertSame('123', $this->bcdCodec()->decode(hex2bin('0123'), 3));
    }

    
    #[Test]
    public function it_strips_a_right_pad_when_decoding_an_odd_value(): void
    {
        $codec = new BcdCodec(BcdPadding::Right);
        
        self::assertSame('123', $codec->decode(hex2bin('1230'), 3));
    }

    #[Test]
    public function it_ignores_the_pad_nibble_value_when_stripping(): void
    {
        // Configured for 0x0 pad, but the wire used 0xF; the pad is stripped regardless.
        $codec = new BcdCodec(BcdPadding::Right);

        self::assertSame('123', $codec->decode(hex2bin('123F'), 3));
    }


    #[Test]
    public function it_decodes_zero_length_to_empty(): void
    {
        self::assertSame('', (new BcdCodec())->decode('', 0));
    }


    #[Test]
    public function it_rejects_a_wrong_byte_count_on_decode(): void 
    {
        $this->expectException(ParseException::class);

        // 4 digits need 2 bytes; only 1 supplied.
        $this->bcdCodec()->decode(hex2bin('12'), 4);
    }

    #[Test]
    public function it_rejects_non_decimal_data_nibbles_on_decode(): void
    {
        $this->expectException(ParseException::class);

        // 0x1A23 -> "1A23": the 'A' nibble is not a decimal digit.
        (new BcdCodec())->decode(hex2bin('1A23'), 4);
    }

    // --- Round trips ------------------------------------------------------

    /**
     * @return iterable<string, array{BcdPadding, int, string}>
     */
    public static function roundTripCases(): iterable
    {
        yield 'even, left'     => [BcdPadding::Left, 0x0, '4111111111111111'];
        yield 'odd, left'      => [BcdPadding::Left, 0x0, '12345'];
        yield 'odd, right 0'   => [BcdPadding::Right, 0x0, '12345'];
        yield 'odd, right F'   => [BcdPadding::Right, 0xF, '4111111111111111112']; // 19-digit PAN
        yield 'amount'         => [BcdPadding::Left, 0x0, '000000012500'];
    }

    #[Test]
    #[DataProvider('roundTripCases')]
    public function it_round_trips(BcdPadding $padding, int $nibble, string $value): void
    {
        $codec = new BcdCodec($padding, $nibble);

        $bytes = $codec->encode($value);

        self::assertSame($value, $codec->decode($bytes, strlen($value)));
    }

    // --- byteLength & construction ---------------------------------------

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function byteLengthCases(): iterable
    {
        yield '0 -> 0'  => [0, 0];
        yield '1 -> 1'  => [1, 1];
        yield '3 -> 2'  => [3, 2];
        yield '4 -> 2'  => [4, 2];
        yield '12 -> 6' => [12, 6];
        yield '19 -> 10' => [19, 10];
    }

    #[Test]
    #[DataProvider('byteLengthCases')]
    public function it_reports_byte_length(int $digits, int $expectedBytes): void
    {
        self::assertSame($expectedBytes, (new BcdCodec())->byteLength($digits));
    }

    #[Test]
    public function it_rejects_a_pad_nibble_out_of_range(): void
    {
        $this->expectException(ValidationException::class);

        new BcdCodec(BcdPadding::Left, 0x10);
    }

    #[Test]
    public function it_rejects_a_negative_length(): void
    {
        $this->expectException(ValidationException::class);

        (new BcdCodec())->byteLength(-1);
    }
}
