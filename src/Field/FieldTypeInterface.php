<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Field;

use Tuurosung\switch8583\Field\DecodedValue;


/**
 * A field type combines a length discipline (fixed, LLVAR, LLLVAR) with the
 * codec(s) needed to put a value on the wire and read it back.
 *
 * Decoding is stream-oriented: it reads from a byte buffer at a given offset
 * and reports how many bytes it consumed, so the message parser can walk a
 * raw message field by field without pre-splitting it.
 */
interface FieldTypeInterface
{

    /**
     * Encode a logical value into its full wire representation, including the
     * length prefix for variable-length disciplines.
     */
    public function encode(string $length): string;


    /**
     * Decode one value from the buffer starting at the given offset.
     */
    public function decode(string $bytes, int $offset = 0): DecodedValue;


    /**
     * The maximum logical length (characters/digits) a value may have.
     */
    public function maxLength(): int;
}