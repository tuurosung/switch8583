<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Codec;


use Tuurosung\switch8583\Codec\BcdPadding;
use Tuurosung\switch8583\Exceptions\EncodingException;
use Tuurosung\switch8583\Exceptions\ParseException;
use Tuurosung\switch8583\Exceptions\ValidationException;


/**
 * Binary-Coded Decimal codec.
 *
 * BCD packs two decimal digits into each byte, one per 4-bit nibble, so "1234"
 * becomes the two bytes 0x12 0x34. A value with an odd number of digits does
 * not fill a whole number of bytes, so a single pad nibble is added to round it
 * up. Two things about that pad vary between fields and networks, and both are
 * configurable here:
 *
 *   - the side it is added on ({@see BcdPadding}), driven by the field's
 *     justification;
 *   - the nibble value used (0x0 by default, sometimes 0xF).
 *
 * Because decimal digits are a subset of hexadecimal digits, encoding is simply
 * a hex-to-binary pack of the (padded) digit string, and decoding is the
 * reverse followed by stripping the pad nibble from the correct side.
 *
 * The instance is immutable; construct one per distinct padding convention.
 */
final class BcdCodec implements CodecInterface
{
    private readonly string $padHex;


    /**
     * @param BcdPadding $padding   Which side to pad an odd-length value on.
     *                              Defaults to {@see BcdPadding::Left} (values
     *                              treated as right-justified).
     * @param int        $padNibble The 4-bit pad value, 0x0..0xF. Defaults to 0x0.
     *
     * @throws ValidationException When the pad nibble is outside 0x0..0xF.
     */
    public function __construct(
        private readonly BcdPadding $padding = BcdPadding::Left,
        int $padNibble = 0x0,
    ){
        if ($padNibble < 0x0 || $padNibble > 0xF) {
            throw new ValidationException(
                sprintf(
                    "BCD Pad nibble must be between 0x0 and 0xF; got 0x%X",
                    $padNibble,
                )
            );
        }

        $this->padHex = strtoupper(dechex($padNibble));
    }



    /**
     * Pack a decimal string into BCD bytes.
     *
     * @throws EncodingException When the value contains non-decimal characters.
     */
    public function encode(string $value): string
    {
        if ($value === '') {
            return '';
        }


        if (!ctype_digit($value)) {
            throw new EncodingException(
                sprintf(
                    'BCD can only encode decimal digits; got "%s".',
                    $value,
                )
            );
        }

        $padded = $value;

        if (strlen($padded) % 2 !== 0) {
            $padded = $this->padding === BcdPadding::Left 
                ? $this->padHex  . $padded
                : $padded . $this->padHex;
        }

        // Safe: $padded is now an even-length string of hex digits (0-9 plus the pad nibble).
        return (string) hex2bin($padded);
    }



    /**
     * Unpack BCD bytes into a decimal string of the given digit length.
     *
     * @throws ValidationException When length is negative.
     * @throws ParseException      When the byte count does not match the length,
     *                             or the data nibbles are not decimal.
     */
    public function decode(string $bytes, int $length): string
    {
        if ($length < 0) {
            throw new ValidationException("Length cannot be negative");
        }

        if ($length === 0) {
            return '';
        }

        $expectedBytes = $this->byteLength($length);

        if (strlen($bytes) !== $expectedBytes) {
            throw new ParseException(
                sprintf(
                    'Expected %d byte(s) for a %d-digit BCD value; got %d',
                    $expectedBytes,
                    $length,
                    strlen($bytes)
                )
            );
        }

        $nibbles = strtoupper(bin2hex($bytes));

        if(strlen($nibbles) > $length) {
            // Odd length: one pad nibble was added; strip it from the pad side.
            $digits = $this->padding === BcdPadding::Left
                ? substr($nibbles, 1)
                : substr($nibbles, 0, $length);
        } else {
            $digits = $nibbles;
        }

        if (!ctype_digit($digits)) {
            throw new ParseException(
                sprintf(
                    'BCD data contains a non-decimal nibble: "%s".',
                    $nibbles
                )
            );
        }

        return $digits;
    }


    public function byteLength(int $length): int
    {
        if ($length < 0) {
            throw new ValidationException("Length cannot be negative");
        }

        return intdiv($length + 1, 2);
    }
}