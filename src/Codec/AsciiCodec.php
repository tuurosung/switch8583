<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Codec;


use Tuurosung\switch8583\Codec\CodecInterface;
use Tuurosung\switch8583\Exceptions\ParseException;
use Tuurosung\switch8583\Exceptions\ValidationException;

/**
 * ASCII codec: a one-byte-per-character mapping.
 *
 * Each character of the logical value occupies exactly one byte on the wire, so
 * encoding is the identity and decoding returns the raw bytes unchanged once the
 * length is verified. Content is not restricted here — numeric, alphanumeric,
 * and special-character fields all use this codec, and any character-set or
 * justification rules belong to the field layer.
 */
final class AsciiCodec implements CodecInterface
{
    public function encode(string $value): string
    {
        return $value;
    }


    
    /**
     * @throws ValidationException When length is negative.
     * @throws ParseException      When the byte count does not match the length.
     */
    public function decode(string $bytes, int $length): string
    {
        if ($length < 0) {
            throw new ValidationException("Length cannot be negative");
        }

        if (strlen($bytes) !== $length) {
            throw new ParseException(
                sprintf(
                    "Expected %d byte(s) for an ASCII value; got %d",
                    $length,
                    strlen($bytes)
                )
            );
        }

        return $bytes;
    }


 
    public function byteLength(int $length): int
    {
        if ($length < 0) {
            throw new ValidationException("Length cannot be negative");
        }

        return $length;
    }
}