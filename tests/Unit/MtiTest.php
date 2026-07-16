<?php

declare(strict_types=1);

namespace Tuurosung\switch8583\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Exceptions\ValidationException;
use Tuurosung\switch8583\Mti;

#[CoversClass(Mti::class)]
final class MtiTest extends TestCase
{
    #[Test]
    public function it_exposes_each_positional_digit(): void
    {
        $mti = Mti::fromString('1420');

        self::assertSame(1, $mti->versionDigit());
        self::assertSame(4, $mti->classDigit());
        self::assertSame(2, $mti->functionDigit());
        self::assertSame(0, $mti->originDigit());
    }

    #[Test]
    public function it_stringifies_to_its_four_digits(): void
    {
        self::assertSame('0100', (string) Mti::fromString('0100'));
        self::assertSame('0100', Mti::fromString('0100')->value);
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function requestResponseCases(): iterable
    {
        // mti, isRequest, isResponse
        yield 'authorization request' => ['0100', true, false];
        yield 'authorization response' => ['0110', false, true];
        yield 'financial advice' => ['0220', true, false];
        yield 'financial advice response' => ['0230', false, true];
        yield 'reversal advice' => ['0420', true, false];
        yield 'network mgmt request' => ['0800', true, false];
        yield 'network mgmt response' => ['0810', false, true];
        yield '1993 authorization request' => ['1100', true, false];
    }

    #[Test]
    #[DataProvider('requestResponseCases')]
    public function it_classifies_requests_and_responses(string $value, bool $isRequest, bool $isResponse): void
    {
        $mti = Mti::fromString($value);

        self::assertSame($isRequest, $mti->isRequest());
        self::assertSame($isResponse, $mti->isResponse());
    }

    #[Test]
    public function it_detects_repeats_from_the_origin_digit(): void
    {
        self::assertFalse(Mti::fromString('0100')->isRepeat());
        self::assertTrue(Mti::fromString('0101')->isRepeat());
        self::assertTrue(Mti::fromString('0421')->isRepeat());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function responsePairs(): iterable
    {
        yield 'auth' => ['0100', '0110'];
        yield 'financial' => ['0200', '0210'];
        yield 'financial advice' => ['0220', '0230'];
        yield 'reversal advice' => ['0420', '0430'];
        yield 'network mgmt' => ['0800', '0810'];
        yield '1993 auth' => ['1100', '1110'];
    }

    #[Test]
    #[DataProvider('responsePairs')]
    public function it_derives_the_response_mti(string $request, string $expected): void
    {
        self::assertSame($expected, (string) Mti::fromString($request)->response());
    }

    #[Test]
    public function response_preserves_the_origin_digit(): void
    {
        self::assertSame('0111', (string) Mti::fromString('0101')->response());
    }

    #[Test]
    public function a_response_has_no_response_of_its_own(): void
    {
        $this->expectException(ValidationException::class);

        Mti::fromString('0110')->response();
    }

    #[Test]
    public function equals_compares_by_value(): void
    {
        self::assertTrue(Mti::fromString('0200')->equals(Mti::fromString('0200')));
        self::assertFalse(Mti::fromString('0200')->equals(Mti::fromString('0210')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'too short' => ['010'];
        yield 'too long' => ['01000'];
        yield 'letters' => ['01A0'];
        yield 'empty' => [''];
        yield 'whitespace' => ['0 10'];
        yield 'signed' => ['-100'];
    }

    #[Test]
    #[DataProvider('invalidInputs')]
    public function it_rejects_anything_but_four_digits(string $input): void
    {
        $this->expectException(ValidationException::class);

        Mti::fromString($input);
    }
}
