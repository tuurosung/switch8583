<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Definitions;

enum FieldFormat
{
    /** The value always occupies exactly the declared length; no prefix. */
    case Fixed;


    /** A two-digit length prefix precedes the value (max 99). */
    case Llvar;


    /** A three-digit length prefix precedes the value (max 999). */
    case Lllvar;


    /**
     * The number of digits in this discipline's length prefix; 0 for fixed.
     */
    public function prefixDigits(): int
    {
        return match ($this) {
            self::Fixed => 0,
            self::Llvar => 2,
            self::Lllvar => 3,
        };
    }


    /**
     * Whether values of this discipline carry a length prefix on the wire.
     */
    public function isVariable(): bool
    {
        return $this !== self::Fixed;
    }
}