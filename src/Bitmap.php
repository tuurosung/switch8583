<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583;

use Tuurosung\switch8583\Exceptions\ParseException;
use Tuurosung\switch8583\Exceptions\ValidationException;


/**
 * An immutable ISO 8583 bitmap.
 *
 * A bitmap declares which data fields are present in a message. Fields are
 * numbered 1..128, most-significant-bit first:
 *
 *   - Bit 1 (the MSB of the first byte) is structural: it signals that a
 *     secondary bitmap (covering fields 65..128) follows. It is NOT a data
 *     field, so it is managed automatically and never appears in
 *     {@see presentFields()}. Setting it explicitly is rejected.
 *   - Bits 2..64 map to fields 2..64 in the primary (8-byte) bitmap.
 *   - Bits 65..128 map to fields 65..128 in the secondary (next 8-byte) bitmap,
 *     which is only emitted when at least one of those fields is present.
 *
 * For a field f, the bit lives in byte intdiv(f - 1, 8) at mask 0x80 >> ((f - 1) % 8).
 *
 * Tertiary bitmaps (fields 129..192, indicated by bit 65 of the secondary
 * bitmap under ISO 8583:1993) are out of scope for this version.
 */

final class Bitmap implements \Countable, \Stringable
{
    /** Lowest field a caller may set (field 1 is structural). */
    public const MIN_FIELD = 2;


    /** Highest field supported without a tertiary bitmap. */
    public const MAX_FIELD = 128;


    /** Fields at or above this number require a secondary bitmap. */
    public const SECONDARY_THRESHOLD = 65;


    private const PRIMARY_BYTES = 8;


    private const SECONDARY_BYTES = 16;


    /**
     * @param list<int> $fields Ascending, unique field numbers, each in
     *     [MIN_FIELD, MAX_FIELD]. Callers reach this
     *     constructor only through the validated named
     *     constructors below.
     */
    private function __construct(
        private readonly  array $fields
    ){}



    public static function fromFields(int ...$fields): self
    {
        $set = [];

        foreach ($fields as $field) {
            self::assertSettableField($field);
            $set[$field] = true;
        }

        ksort($set);

        return new self(array_keys($set));
    }



    /**
     * Parse a bitmap from its raw binary representation (8 or 16 bytes).
     *
     * The secondary-bitmap indicator (bit 1) must agree with the byte length:
     * 8 bytes with the indicator set, or 16 bytes with it clear, are both
     * rejected as inconsistent rather than silently accepted.
     *
     * @throws ParseException When the length is wrong or inconsistent with the
     *                        secondary-bitmap indicator.
     */
    public static function fromBinary(string $bytes): self
    {
        $length = strlen($bytes);

        if ($length !== self::PRIMARY_BYTES && $length !== self::SECONDARY_BYTES) {
            throw new ParseException(
                sprintf(
                    'A bitmap must be %d or %d; got %d.',
                    self::PRIMARY_BYTES,
                    self::SECONDARY_BYTES,
                    $length,
                )
            );
        }

        /** @var list<int> $octets 0-indexed unsigned byte values. */
        $octets = array_values(unpack('C*', $bytes));
        $hasSecondary = ($octets[0] & 0x80) === 0x80;

        if ($hasSecondary && $length !== self::SECONDARY_BYTES) {
            throw new ParseException(
                'The bitmap sets the secondary indicator but it is only 8 bytes; 16 were expected',
            );
        }

        if (!$hasSecondary && $length !== self::PRIMARY_BYTES) {
            throw new ParseException(
                'The bitmap is 16 bytes  but does not set the secondary indicator; the input is inconsistent.',
            );
        }

        $fields = [];

        for($byteIndex = 0; $byteIndex < $length; $byteIndex++) {
            $octet = $octets[$byteIndex];

            for($bit = 0; $bit < 8; $bit++) {

                if (($octet & (0x80 >> $bit)) === 0) {
                    continue;
                }

                $field = ($byteIndex * 8) + $bit + 1;

                // Field 1 is the structural secondary-bitmap indicator, not data.
                if ($field === 1) {
                    continue;
                }

                $fields[] = $field;
            }
        }

        // Bits are read in ascending order, so $fields is already sorted & unique.
        return new self($fields);
    }


    /**
     * Parse a bitmap from a hexadecimal string (16 or 32 hex characters).
     *
     * Input is case-insensitive; surrounding whitespace is trimmed.
     *
     * @throws ParseException When the string is not valid, even-length hex.
     */
    public static function fromHex(string $hex): self
    {
        $hex = trim($hex);

        if ($hex === '' || (strlen($hex) % 2) !== 0 || !ctype_xdigit($hex)) {
            throw new ParseException(
                sprintf(
                    'Invalid hexadecimal  bitmap: "%s".', $hex
                )
            );
        }

        $binary = hex2bin($hex);

        if ($binary === false) {
            throw new ParseException(
                sprintf(
                    'Invalid hexadecimal bitmap: "%s".', $hex
                )
            );
        }

        return self::fromBinary($binary);
    }


    /**
     * Return a new bitmap with the given field(s) additionally set.
     *
     * @throws ValidationException When a field number is invalid.
     */
    public function with(int ...$fields): self
    {
        return self::fromFields(...$this->fields, ...$fields);
    }


    /**
     * Return a new bitmap with the given field(s) cleared.
     *
     * Removing a field that is not present is a no-op.
     */
    public function without(int ...$fields): self
    {
        $remove = array_flip($fields);

        $kept = array_values(
            array_filter($this->fields, static fn(int $field): bool => !isset($remove[$field])),
        );

        return new self($kept);
    }


    /**
     * Whether the given data field is present.
     *
     * @throws ValidationException When the field number is out of range or is
     *                             the reserved structural field 1.
     */
    public function has(int $field): bool
    {
        self::assertSettableField($field);

        return in_array($field, $this->fields, true);
    }


    /**
     * The present data fields, ascending. Never includes the structural field 1.
     *
     * @return list<int>
     */
    public function presentFields(): array
    {
        return $this->fields;
    }


    /**
     * Whether serialising this bitmap requires a secondary bitmap, i.e. whether
     * any field in the 65..128 range is present.
     */
    public function hasSecondaryBitmap(): bool
    {
        return $this->fields !== []
            && $this->fields[array_key_last($this->fields)] >= self::SECONDARY_THRESHOLD;
    }


    /**
     * Serialise to raw binary: 8 bytes, or 16 when a secondary bitmap is needed.
     */
    public function toBinary(): string
    {
        $byteCount = $this->hasSecondaryBitmap() ? self::SECONDARY_BYTES : self::PRIMARY_BYTES;

        /** @var list<int> $octets */
        $octets = array_fill(0, $byteCount,0);

        if ($byteCount === self::SECONDARY_BYTES) {
            $octets[0] |= 0x80; // Field 1: secondary bitmap present.
        }

        foreach ($this->fields as $field) {
            $byteIndex = intdiv($field - 1, 8);
            $octets[$byteIndex] |= 0x80 >> (($field - 1) % 8);
        }

        return pack('C*', ...$octets);
    }


    /**
     * Serialise to an uppercase hexadecimal string (16 or 32 characters).
     */
    public function toHex(): string
    {
        return strtoupper(bin2hex($this->toBinary()));
    }


    /**
     * The number of data fields present.
     */
    public function count(): int
    {
        return count($this->fields);
    }


    public function __toString(): string
    {
        return $this->toHex();
    }


    /**
     * @throws ValidationException
     */
    private static function assertSettableField(int $field): void
    {
        if ($field === 1) {
            throw new ValidationException(
                'Field 1 is the secondary bitmap indicator; it is managed automatically and cannot be set.',
            );
        }

        if ($field < self::MIN_FIELD || $field > self::MAX_FIELD) {
            throw new ValidationException(
                sprintf(
                    'Field number %d is out of range; expected %d..%d',
                    $field,
                    self::MIN_FIELD,
                    self::MAX_FIELD
                )
            );
        }
    }
}