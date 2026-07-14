<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Codec;

/**
 * Which side a pad nibble is added to when a BCD value has an odd number of
 * digits and must be rounded up to a whole byte.
 *
 * The correct choice depends on the field's justification:
 *
 *   - {@see Left}: the value is right-justified, so the pad nibble goes on the
 *     left (leading). This is the usual convention for numeric quantities such
 *     as amounts and processing codes — "123" becomes 0x0123.
 *
 *   - {@see Right}: the value is left-justified, so the pad nibble goes on the
 *     right (trailing). This is common for variable-length identifiers such as
 *     a primary account number — "123" becomes 0x1230 (or 0x123F when the pad
 *     nibble is 0xF).
 */
enum BcdPadding
{
    case Left;
    case Right;
}