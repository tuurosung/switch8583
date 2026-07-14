<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Codec;


/**
 * A codec maps a field's logical value (a string of characters or digits) to
 * and from its raw on-the-wire byte representation.
 *
 * The two implementations differ in how many bytes a value occupies:
 *   - {@see AsciiCodec}: one byte per character (1:1).
 *   - {@see BcdCodec}: two decimal digits packed per byte (2:1).
 *
 * Justification and length framing (fixed vs LLVAR vs LLLVAR, padding a value
 * out to a field's declared length) are the responsibility of the field layer,
 * not the codec. A codec receives a value that is already the correct logical
 * length and concerns itself only with the character-to-byte mapping.
 */
interface CodecInterface
{
    /**
     * Encode a logical value into its raw byte representation.
     */
    public function encode(string $value): string;


    /**
     * Decode raw bytes back into a logical value of the given length.
     *
     * @param string $bytes  The exact raw bytes for this value.
     * @param int    $length The number of logical characters/digits expected.
     *                       Required because the byte representation alone can
     *                       be ambiguous (e.g. a padded odd-length BCD value).
     */
    public function decode(string $bytes, int $length): string;


    /**
     * The number of raw bytes a value of the given logical length occupies.
     */
    public function byteLength(int $length): int;
}