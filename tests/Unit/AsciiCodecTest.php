<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Codec\AsciiCodec;
use Tuurosung\switch8583\Exceptions\ParseException;
use Tuurosung\switch8583\Exceptions\ValidationException;

final class AsciiCodecTest extends TestCase
{
    private function asciiCodec()
    {
        return new AsciiCodec();
    }


    #[Test]
    public function it_encodes_a_value_one_byte_per_character(): void
    {
        self::assertSame('TERM0001', $this->asciiCodec()->encode('TERM0001'));
        self::assertSame(8, strlen($this->asciiCodec()->encode('TERM0001')));
    }


    #[Test]
    public function it_round_trips_a_value(): void
    {
        $value = 'ABC=123?';

        self::assertSame($value, $this->asciiCodec()->decode(
            $this->asciiCodec()->encode($value), strlen($value)
        ));
    }


    #[Test]
    public function it_handles_empty_values(): void
    {
        self::assertSame('', $this->asciiCodec()->encode(''));
        self::assertSame('', $this->asciiCodec()->decode('', 0));
    }



    #[Test]
    public function it_reports_byte_length_as_character_count(): void
    {
        self::assertSame(12, $this->asciiCodec()->byteLength(12));
        self::assertSame(0, $this->asciiCodec()->byteLength(0));
    }


    #[Test]
    public function it_rejects_a_length_mismatch_on_decode(): void
    {
        $this->expectException(ParseException::class);

        $this->asciiCodec()->decode('123', 4);
    }


    #[Test]
    public function it_rejects_a_negative_length(): void
    {
        $this->expectException(ValidationException::class);

        $this->asciiCodec()->byteLength(-1);
    }
}