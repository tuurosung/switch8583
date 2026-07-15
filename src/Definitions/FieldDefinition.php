<?php 

declare(strict_types= 1);

namespace Tuurosung\switch8583\Definitions;

use Tuurosung\switch8583\Bitmap;
use Tuurosung\switch8583\Codec\BcdPadding;
use Tuurosung\switch8583\Definitions\FieldEncoding;
use Tuurosung\switch8583\Definitions\FieldFormat;
use Tuurosung\switch8583\Exceptions\ValidationException;
use Tuurosung\switch8583\Field\FieldTypeInterface;
use Tuurosung\switch8583\Field\FixedField;
use Tuurosung\switch8583\Field\LllVarField;
use Tuurosung\switch8583\Field\LlVarField;

final readonly class FieldDefinition
{
    public function __construct(
        public int $number,
        public string $name,
        public FieldFormat $format,
        public int $length,
        public FieldEncoding $encoding,
        public FieldEncoding $lengthEncoding,
        public BcdPadding $bcdPadding,
        public int $bcdPadNibble
    ) {
        
        // validate the bitmap's length
        if ($number < Bitmap::MIN_FIELD || $number > Bitmap::MAX_FIELD) {
            throw new ValidationException(
                sprintf(
                    'Field number %d is out of range; expected $d..%d',
                    $number,
                    Bitmap::MIN_FIELD,
                    Bitmap::MAX_FIELD
                )
            );
        }

        // validate the name field
        if ($name === '') {
            throw new ValidationException(
                sprintf(
                    'The field $d need\'s a non-empty name',
                    $number
                )
            );
        }

        /**
         * Authoritatively enforce the length bounds according to t
         * the field type constructors; If the code fails here, the error
         * remains at the Definition layer where we can easily fix it
         */
        if ($length < 1) {
            throw new ValidationException(
                sprintf(
                    'Field %d needs a positive length; got %d',
                    $number,
                    $length,                    
                )
            );
        }
    }


    /**
     * A fixed-length element: the value is always exactly $length characters.
     */
    public static function fixed(
        int $number,
        string $name,
        int $length,
        FieldEncoding $encoding,
    ){
        return new self(
            number: $number,
            name: $name,
            format: FieldFormat::Fixed,
            length: $length,
            encoding: $encoding,
            lengthEncoding: $encoding, // Fixed fields do not use this, just for consistency
            bcdPadding: BcdPadding::Left,
            bcdPadNibble: 0x0
        );
    }



    /**
     * An LLVAR element: up to $maxLength characters behind a 2-digit prefix.
     *
     * The prefix encoding defaults to the data encoding, which is the common
     * arrangement; pass $lengthEncoding when a network frames one encoding
     * with the other.
     */
    public static function llvar(
        int $number,
        string $name,
        int $maxLength,
        FieldEncoding $encoding,
        ?FieldEncoding $lengthEncoding = null
    ){
        return new self(
            number: $number,
            name: $name,
            format: FieldFormat::Llvar,
            length: $maxLength,
            encoding: $encoding,
            lengthEncoding: $lengthEncoding ?? $encoding,
            bcdPadding: BcdPadding::Left,
            bcdPadNibble: 0x0
        );
    }


    /**
     * An LLLVAR element: up to $maxLength characters behind a 3-digit prefix.
     */
    public static function Lllvar(
        int $number,
        string $name,
        int $maxLength,
        FieldEncoding $encoding,
        ?FieldEncoding $lengthEncoding = null
    ){
        return new self(
            number: $number,
            name: $name,
            format: FieldFormat::Lllvar,
            length: $maxLength,
            encoding: $encoding,
            lengthEncoding: $lengthEncoding ?? $encoding,
            bcdPadding: BcdPadding::Left,
            bcdPadNibble: 0x0
        );
    }


    /**
     * A copy of this definition with a different BCD padding convention —
     * e.g. the PAN's left-justified, trailing-0xF arrangement.
     *
     * @throws ValidationException When the pad nibble is outside 0x0..0xF, or
     *     the field's data is not BCD-encoded (padding
     *     is meaningless there, so requesting it is a
     *     definition bug worth failing on).
     */
    public function withBcdPadding(BcdPadding $padding, int $padNibble = 0x0): self
    {
        // validate encoding
        if ($this->encoding !== FieldEncoding::Bcd) {
            throw new ValidationException(
                sprintf(
                    'Field %d ("%s") is not BCD encoded; a BCD padding convention does not apply.',
                    $this->number,
                    $this->name
                )
            );
        }

        // validate nibbles
        if ($padNibble < 0x0 || $padNibble > 0xF) {
            throw new ValidationException(
                sprintf(
                    'BCD pad nibble must be between 0x0 and 0xF; got 0x%X',
                    $padNibble
                )
            );
        }

        return new self(
            number: $this->number,
            name: $this->name,
            format: $this->format,
            length: $this->length,
            encoding: $this->encoding,
            lengthEncoding: $this->lengthEncoding,
            bcdPadding: $padding,
            bcdPadNibble: $padNibble
        );
    }


    /**
     * Build the executable field type this definition describes.
     */
    public function fieldType(): FieldTypeInterface
    {
        $dataCodec = $this->encoding->codec($this->bcdPadding, $this->bcdPadNibble);

        return match($this->format) {
            FieldFormat::Fixed => new FixedField($this->length, $dataCodec),
            FieldFormat::Llvar => new LlVarField(
                $this->length,
                $dataCodec,
                $this->lengthEncoding->codec()
            ),
            FieldFormat::Lllvar => new LllVarField(
                $this->length,
                $dataCodec,
                $this->lengthEncoding->codec(),
            )
        };
    }
}