<?php

namespace Tuurosung\switch8583\Definitions;

use Tuurosung\switch8583\Codec\AsciiCodec;
use Tuurosung\switch8583\Codec\BcdCodec;
use Tuurosung\switch8583\Codec\BcdPadding;
use Tuurosung\switch8583\Codec\CodecInterface;

/**
 * How a field's logical value maps to bytes on the wire.
 *
 * The enum owns the intrinsic fact of which codec implements each encoding;
 * everything configurable about that codec (BCD padding side, pad nibble) is
 * supplied by the field definition, because it varies per field, not per
 * encoding.
 */
enum FieldEncoding
{
    /** One byte per character. Alphanumeric and special-character data. */
    case Ascii;


    /** Two decimal digits packed per byte. Numeric data. */
    case Bcd;


    /**
     * Raw bytes carried as-is, one byte per unit of length (PIN blocks, MACs,
     * ICC data). Mechanically identical to {@see self::Ascii} — an identity
     * mapping with a length check — so it shares the codec; it exists as a
     * distinct case because definitions read differently ("b 64" vs "ans 8")
     * and later tooling (masking, dumps) will want to distinguish them.
     */
    case Binary;


    public function codec(
        BcdPadding $bcdPadding = BcdPadding::Left,
        int $bcdPadNibble = 0x0,
    ): CodecInterface {

        return match($this) {
            self::Ascii, self::Binary => new AsciiCodec(),
            self::Bcd => new BcdCodec($bcdPadding, $bcdPadNibble)
        };

    }
}