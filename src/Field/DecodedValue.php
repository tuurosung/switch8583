<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Field;


/**
 * The result of decoding one field from a byte stream: the logical value and
 * the total number of raw bytes it consumed (length prefix included, for
 * variable-length fields).
 *
 * The consumed count is what lets the message parser advance its offset and
 * read the next field, so it always reflects on-the-wire bytes, never logical
 * characters.
 */
final readonly class DecodedValue
{
    private function __construct(
        public string $value, 
        public int $bytesConsumed,
    ) {}

    
    public static function of(string $value, int $bytesConsumed): self
    {
        return new self($value, $bytesConsumed);
    }
}