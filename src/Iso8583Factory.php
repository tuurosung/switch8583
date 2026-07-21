<?php

declare (strict_types = 1);

namespace Tuurosung\switch8583;

use Tuurosung\switch8583\Definitions\FieldEncoding;
use Tuurosung\switch8583\Definitions\Iso8583v1987;
use Tuurosung\switch8583\Definitions\Iso8583v1993;
use Tuurosung\switch8583\Definitions\VersionInterface;
use Tuurosung\switch8583\Exceptions\ValidationException;
use Tuurosung\switch8583\Message;
use Tuurosung\switch8583\Mti;

/**
 * A small factory that pins a version catalogue and MTI encoding once, so
 * calling code stops repeating them on every {@see Message::make()} and
 * {@see Message::parse()} call.
 *
 * This class is deliberately framework-free — the Laravel service provider is
 * a thin wrapper that constructs one of these from config. Plain PHP,
 * Symfony, or any other host can new it up directly:
 *
 *     $factory = Iso8583Factory::for('1987');
 *     $request = $factory->make('0200')->withField(3, '000000');
 *     $parsed  = $factory->parse($rawBytes);
 */
final readonly class Iso8583Factory
{
    public function __construct(
        private VersionInterface $version,
        private FieldEncoding $mtiEncoding = FieldEncoding::Ascii
    ){}


    /**
     * Build a factory from string configuration, the form config files carry.
     *
     * @param string $version     "1987" or "1993".
     * @param string $mtiEncoding "ascii" or "bcd".
     *
     * @throws ValidationException When either value is unrecognised.
     */
    public static function for(string $version = '1987', string $mtiEncoding = 'ascii')
    {
        return new self(
            self::resolveVersion($version),
            self::resolveMtiEncoding($mtiEncoding)
        );
    }


    /**
     * Start building a message on the configured version and MTI encoding.
     */
    public function make(Mti|string $mti): Message
    {
        return Message::make($mti, $this->version, $this->mtiEncoding);
    }


    /**
     * Parse raw bytes using the configured version and MTI encoding.
     */
    public function parse(string $bytes): Message
    {
        return Message::parse($bytes, $this->version, $this->mtiEncoding);
    }


    public function version(): VersionInterface
    {
        return $this->version;
    }


    private static function resolveVersion(string $version): VersionInterface
    {
        return match ($version) {
            '1987' => new Iso8583v1987(),
            '1993' => new Iso8583v1993(),
            default => throw new ValidationException(
                sprintf(
                    'Unknown ISO 8583 version "%s"; expected "1987" or "1993"',
                    $version
                )
            ),
        };
    }


    private static function resolveMtiEncoding(string $mtiEncoding): FieldEncoding
    {
        return match (strtolower($mtiEncoding)) {
            'ascii' => FieldEncoding::Ascii,
            'bcd' => FieldEncoding::Bcd,
            default => throw new ValidationException(
                sprintf(
                    'Unknown MTI Encoding "%s": expected "ascii" or "bcd".',
                    $mtiEncoding
                )
            ),
        };
    }
} 