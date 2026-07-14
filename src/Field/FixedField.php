<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Field;


use Tuurosung\switch8583\Codec\CodecInterface;
use Tuurosung\switch8583\Exceptions\ParseException;
use Tuurosung\switch8583\Exceptions\ValidationException;


/**
 * A fixed-length field: the value always occupies exactly the declared number
 * of logical characters/digits, and no length prefix appears on the wire.
 *
 * This class is deliberately strict: the caller must supply a value of exactly
 * the declared length. It never pads silently, because correct padding depends
 * on the field's justification (amounts zero-pad on the left, identifiers
 * space-pad on the right) and a wrong silent guess corrupts data while looking
 * successful. Justification-aware padding helpers belong to the layer that
 * knows what each field means — the field definitions — not here.
 */
final class FixedField implements FieldTypeInterface
{
    /**
     * @param int $length The exact logical length of the value.
     * @param CodecInterface  $codec  How the value's characters map to bytes.
     *
     * @throws ValidationException When the length is not positive.
     */
    public function __construct(
        private readonly int $length,
        private readonly CodecInterface $codec
    ){
        if ($length < 1) {
            throw new ValidationException(
                sprintf(
                    'A fixed field requires a positive length; got %d.',
                    $length,
                )
            );
        }
    }

    
    public function encode(string $value): string
    {
        if (strlen($value) !== $this->length) {
            throw new ValidationException(
                sprintf(
                    'This fixed field takes exactly %d character(s); got %d ("%s"). '
                    . 'Pad the value according to the field\'s justification before encoding.',
                    $this->length,
                    strlen($value),
                    $value
                )
            );
        }

        return $this->codec->encode($value);
    }


    public function decode(string $bytes, int $offset = 0): DecodedValue
    {
        $byteCount = $this->codec->byteLength($this->length);
        $slice = substr($bytes, $offset, $byteCount);

        if (strlen($slice) < $byteCount) {
            throw new ParseException(sprintf(
                'Truncated fixed field: needed %d byte(s) at offset %d, only %d available.',
                $byteCount,
                $offset,
                strlen($slice),
            ));
        }

        return DecodedValue::of(
            $this->codec->decode($slice, $this->length),
            $byteCount,
        );
    }


    public function maxLength(): int
    {
        return $this->length;
    }
}