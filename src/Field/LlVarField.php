<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Field;

/**
 * LLVAR: a variable-length field whose length prefix is two digits, so values
 * of up to 99 logical characters/digits can be expressed. Field 2 (PAN) and
 * field 35 (Track 2) are the classic examples.
 */
final class LlVarField extends VariableLengthField
{
    protected function prefixDigits(): int
    {
        return 2;
    }

    
    protected function disciplineName(): string
    {
        return 'LLVAR';
    }
}