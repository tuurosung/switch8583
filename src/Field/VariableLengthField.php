<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Field;

use Tuurosung\switch8583\Codec\CodecInterface;
use Tuurosung\switch8583\Exceptions\ParseException;
use Tuurosung\switch8583\Exceptions\ValidationException;

/**
 * Shared implementation for the variable-length disciplines (LLVAR, LLLVAR).
 *
 * On the wire a variable-length value is a numeric length prefix followed by
 * the data. Three details matter, and all are frequent sources of
 * interoperability bugs:
 *
 *   - The prefix counts the value's LOGICAL length (characters/digits), not
 *     its byte length. A 16-digit BCD PAN occupies 8 bytes, but its LLVAR
 *     prefix says 16.
 *
 *   - The prefix has its own encoding, independent of the data's. A network
 *     may send a BCD prefix before ASCII data, or vice versa; this class takes
 *     two codecs so every combination is expressible.
 *
 *   - The prefix is zero-padded to its full digit count ("16" is sent as "016"
 *     in an LLLVAR prefix), which also fixes its on-wire byte size: with a BCD
 *     prefix codec an LLVAR prefix is 1 byte and an LLLVAR prefix is 2; with an
 *     ASCII prefix codec they are 2 and 3 bytes respectively.
 */
abstract class VariableLengthField implements FieldTypeInterface
{
    /**
     * @param int $maxLength Longest logical value permitted.
     * @param CodecInterface $dataCodec Codec for the value itself.
     * @param CodecInterface $lengthCodec Codec for the numeric length prefix.
     *
     * @throws ValidationException When the max length is not positive or
     *       exceeds what the prefix digit count can express.
     */
    public function __construct(
        private readonly int $maxLength,
        private readonly CodecInterface $dataCodec,
        private readonly CodecInterface $lengthCodec
    ){
        
        // set the ceiling
        $ceiling = (10 ** $this->prefixDigits()) - 1;

        if ($maxLength < 1 || $maxLength > $ceiling) {
            throw new ValidationException(
                sprintf(
                    "An %s field takes max length of 1..%d.",
                    $this->disciplineName(),
                    $ceiling,
                    $maxLength
                )
            );
        }
    }


    /** The number of digits in this discipline's length prefix (2 for LLVAR, 3 for LLLVAR). */
    abstract protected function prefixDigits(): int;


    /** Human-readable discipline name for error messages. */
    abstract protected function disciplineName(): string;


    public function encode(string $value): string
    {
        $length = strlen($value);

        if ($length > $this->maxLength) {
            throw new ValidationException(
                sprintf(
                    "This %s field takes at most %d character(s); got %d",
                    $this->disciplineName(),
                    $this->maxLength,
                    $length,
                )
            );
        }

        $prefix = str_pad((string) $length, $this->prefixDigits(), '0', STR_PAD_LEFT);

        return $this->lengthCodec->encode($prefix) . $this->dataCodec->encode($value);
    }



    public function decode(string $bytes, int $offset = 0): DecodedValue
    {
        $prefixDigits = $this->prefixDigits();
        $prefixBytes = $this->lengthCodec->byteLength($prefixDigits);

        $prefixSlice  = substr($bytes, $offset, $prefixBytes);

        // Check if the $prefixSlice is the same length as the $prefixBytes
        if (strlen($prefixSlice) < $prefixBytes) {
            throw new ParseException(
                sprintf(
                    'Truncated %s length prefix; needed %d byte(s) at offset %d, only %d available',
                    $this->disciplineName(),
                    $prefixBytes,
                    $offset,
                    strlen($prefixSlice)
                )
            );
        }

        $prefix = $this->lengthCodec->decode($prefixSlice, $prefixDigits);

        // Check if the character type is a digit
        if (! ctype_digit($prefix)) {
            throw new ParseException(
                sprintf(
                    'Invalid %s length prefix "%s" at offset %d',
                    $this->disciplineName(),
                    $prefix,
                    $offset
                )
            );
        }

        $dataLength = (int) $prefix;

        // check for length matches between $dataLength and $maxLength
        if ($dataLength > $this->maxLength) {
            throw new ParseException(
                sprintf(
                    '%s length prefix declares %d character(s), above this field\'s maximum of %d',
                    $this->disciplineName(),
                    $dataLength,
                    $this->maxLength,
                )
            );
        }

        // Handle zero dataLength
        if ($dataLength === 0) {
            return DecodedValue::of('', $prefixBytes);
        }

        // set databytes and dataslice
        $dataBytes = $this->dataCodec->byteLength($dataLength);
        $dataSlice = substr($bytes, $offset + $prefixBytes, $dataBytes);

        // ensure the lengths of dataSlice and dataBytes match
        if (strlen($dataSlice) < $dataBytes) {
            throw new ParseException(
                sprintf(
                    'Truncated %s data; prefix declares %d character(s) (%d byte(s)) at offset %d, only %d available.',
                    $this->disciplineName(),
                    $dataLength,
                    $dataBytes,
                    $offset + $prefixBytes,
                    strlen($dataSlice)
                )
            );
        }


        return DecodedValue::of(
            $this->dataCodec->decode($dataSlice, $dataLength),
            $prefixBytes + $dataBytes
        );
    }

    
    public function maxLength(): int
    {
        return $this->maxLength;
    }
}