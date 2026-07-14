<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Field;


use Tuurosung\switch8583\Field\VariableLengthField;

/**
 * LLLVAR: a variable-length field whose length prefix is three digits, so
 * values of up to 999 logical characters/digits can be expressed. Field 55
 * (ICC/EMV data) and the private-use 12x fields are the classic examples.
 */
final class LllVarField extends VariableLengthField
{
    protected function prefixDigits(): int
    {
        return 3;
    }


    protected function disciplineName(): string
    {
        return 'LLLVAR';
    }
}