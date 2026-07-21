<?php

declare(strict_types = 1);

namespace Tuurosung\switch8583\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Definitions\FieldEncoding;
use Tuurosung\switch8583\Definitions\Iso8583v1987;
use Tuurosung\switch8583\Definitions\Iso8583v1993;
use Tuurosung\switch8583\Exceptions\ValidationException;
use Tuurosung\switch8583\Iso8583Factory;
use Tuurosung\switch8583\Message;

#[CoversClass(Iso8583Factory::class)]
final class Iso8583FactoryTest extends TestCase
{
    #[Test]
    public function it_makes_a_message_on_the_configured_version(): void
    {
        $factory = Iso8583Factory::for('1987');

        $message = $factory->make('0200')->withField(3, '000000');

        self::assertSame('0200', $message->mti()->value);
        self::assertInstanceOf(Iso8583v1987::class, $factory->version());
        self::assertInstanceOf(Message::class, $message);
    }

    #[Test]
    public function it_resolves_the_1993_catalogue(): void
    {
        self::assertInstanceOf(Iso8583v1993::class, Iso8583Factory::for('1993')->version());
    }

    #[Test]
    public function make_then_parse_round_trips_through_the_factory(): void
    {
        $factory = Iso8583Factory::for('1987');

        $original = $factory->make('0200')
            ->withField(3, '000000')
            ->withField(4, '000000012500')
            ->withField(11, '000001');

        $parsed = $factory->parse($original->toBytes());

        self::assertSame($original->toBytes(), $parsed->toBytes());
        self::assertSame('000000012500', $parsed->getField(4));
    }

    #[Test]
    public function a_bcd_mti_factory_packs_the_mti(): void
    {
        $factory = Iso8583Factory::for('1987', 'bcd');

        $wire = $factory->make('0200')->withField(3, '000000')->toBytes();

        // BCD MTI occupies two bytes.
        self::assertSame('0200', strtoupper(bin2hex(substr($wire, 0, 2))));
        self::assertSame('000000', $factory->parse($wire)->getField(3));
    }

    #[Test]
    public function the_constructor_accepts_pre_resolved_dependencies(): void
    {
        $factory = new Iso8583Factory(new Iso8583v1987(), FieldEncoding::Bcd);

        self::assertInstanceOf(Iso8583v1987::class, $factory->version());
        self::assertSame('0200', strtoupper(bin2hex(substr($factory->make('0200')->toBytes(), 0, 2))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badVersions(): iterable
    {
        yield '2003 unsupported' => ['2003'];
        yield 'garbage' => ['nope'];
        yield 'empty' => [''];
    }

    #[Test]
    #[DataProvider('badVersions')]
    public function it_rejects_an_unknown_version(string $version): void
    {
        $this->expectException(ValidationException::class);

        Iso8583Factory::for($version);
    }

    #[Test]
    public function it_rejects_an_unknown_mti_encoding(): void
    {
        $this->expectException(ValidationException::class);

        Iso8583Factory::for('1987', 'ebcdic');
    }

    #[Test]
    public function mti_encoding_is_case_insensitive(): void
    {
        $wire = Iso8583Factory::for('1987', 'BCD')->make('0200')->toBytes();

        self::assertSame('0200', strtoupper(bin2hex(substr($wire, 0, 2))));
    }
}