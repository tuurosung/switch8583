<?php

declare(strict_types = 1);

namespace Tuurosung\switch8583;


use Tuurosung\switch8583\Definitions\FieldEncoding;
use Tuurosung\switch8583\Definitions\VersionInterface;
use Tuurosung\switch8583\Exceptions\ParseException;
use Tuurosung\switch8583\Exceptions\Switch8583Exception;
use Tuurosung\switch8583\Exceptions\ValidationException;

/**
 * An immutable ISO 8583 message: an MTI, a version catalogue, and a set of
 * field values. The bitmap is never stored — it is derived from the present
 * field numbers at serialisation time, so it cannot disagree with the fields.
 *
 * Building:
 *
 *     $request = Message::make('0200', new Iso8583v1987())
 *         ->withField(3, '000000')
 *         ->withField(4, '000000012500')
 *         ->withField(11, '000001')
 *         ->withField(41, 'TERM0001')
 *         ->withField(49, '936');
 *
 *     $wire = $request->toBytes();   // or ->toHex() for logging
 *
 * Parsing walks the same path in reverse — MTI, bitmap, then each present
 * field in ascending order, advancing by each field's consumed byte count:
 *
 *     $response = Message::parse($raw, new Iso8583v1987());
 *     $response->getField(39);
 *
 * The MTI's own wire encoding (4 ASCII bytes, or 2 BCD bytes on packed
 * networks) is a message-level convention, so both {@see self::make()} and
 * {@see self::parse()} accept it as a parameter; the fields' encodings come
 * from the version catalogue.
 *
 * Values are logical strings exactly as the field definitions expect them —
 * already justified/padded for fixed fields. Validation of length and
 * character set happens at serialisation, where the field types enforce it.
 */

final class Message implements \Stringable
{
    public function __construct(
        public readonly Mti $mti,
        public readonly VersionInterface $version,
        public readonly array $fields,
        public readonly FieldEncoding $mtiEncoding
    ){}


    /**
     * @param array<int, string> $fields Field number => logical value, ksorted.
     */
    public static function make(
        Mti|string $mti,
        VersionInterface $version,
        FieldEncoding $mtiEncoding = FieldEncoding::Ascii,
    ): self
    {
        return new self(
            is_string($mti) ? Mti::fromString($mti) : $mti,
            $version,
            [],
            $mtiEncoding,
         );
    }


    /**
     * A copy of this message with one field set (or replaced).
     *
     * The field must exist in the version catalogue — an unknown number fails
     * here, at the call site, rather than at serialisation three layers away.
     *
     * @throws ValidationException When the version does not define the field.
     */
    public function withField(int $number, string $value): self
    {
        // validation for undefined fields
        $this->version->field($number); // this would throw an error

        $fields = $this->fields;
        $fields[$number] = $value;
        ksort($fields);

        return new self(
            $this->mti,
            $this->version,
            $fields,
            $this->mtiEncoding
        );
    }


    /**
     * A copy of this message without the given field. Removing an absent
     * field is a no-op.
     */
    public function withoutField(int $number): self
    {
        $fields = $this->fields;
        unset($fields[$number]);

        return new self(
            $this->mti,
            $this->version,
            $fields,
            $this->mtiEncoding
        );
    }


    /**
     * A copy of this message with a different MTI. The natural companion to
     * {@see Mti::response()} when turning a parsed request into its reply:
     *
     *     $reply = $request->withMti($request->mti()->response())
     *         ->withField(39, '00');
     */
    public function withMti(Mti | string $mti): self
    {
        return new self(
            is_string($mti) ? Mti::fromString($mti) : $mti,
            $this->version,
            $this->fields,
            $this->mtiEncoding
        );
    }


    public function mti(): Mti
    {
        return $this->mti;
    }


    public function version(): VersionInterface
    {
        return $this->version;
    }


    public function hasField(int $number): bool
    {
        return isset($this->fields[$number]);
    }


    /**
     * @throws ValidationException When the field is not present on this message.
     */
    public function getField(int $number): string
    {
        if (! isset($this->fields[$number])) {
            throw new ValidationException(
                sprintf(
                    'Field %d is not present in this %s message',
                    $number,
                    $this->mti->value
                )
            );
        }

        return $this->fields[$number];
    }


    /**
     * All present fields, number => logical value, ascending.
     *
     * @return array<int, string>
     */
    public function fields(): array
    {
        return $this->fields;
    }


    /**
     * The bitmap this message's fields imply. Derived, never stored.
     */
    public function bitmap(): Bitmap
    {
        return Bitmap::fromFields(...array_keys($this->fields));
    }


    /**
     * Serialise to raw wire bytes: MTI, bitmap, then each field ascending.
     *
     * @throws Switch8583Exception When any value violates its field definition.
     */
    public function toBytes(): string
    {
        $wire = $this->mtiEncoding->codec()->encode($this->mti->value)
            . $this->bitmap()->toBinary();

        foreach ($this->fields as $number => $value) {
            $wire .= $this->version->field($number)->fieldType()->encode($value);
        }

        return $wire;
    }


    /**
     * The wire bytes as an uppercase hexadecimal string. The form to log,
     * diff, and paste into test fixtures.
     */
    public function toHex(): string
    {
        return strtoupper(bin2hex($this->toBytes()));
    }


    public static function parse(
        string $bytes,
        VersionInterface $version,
        FieldEncoding $mtiEncoding = FieldEncoding::Ascii,
    ): self {

        $offset = 0;
        $total = strlen($bytes);

        // ---- MTI ----------------------------------------------

        $mtiCodec = $mtiEncoding->codec();
        $mtiBytes = $mtiCodec->byteLength(4);

        // validate length
        if ($total < $bytes) {
            throw new ParseException(
                sprintf(
                    'Truncated message: needed %d byte(s) for the MTI, got %d',
                    $mtiBytes,
                    $total
                )
            );
        }


        try {
            $mti = Mti::fromString($mtiCodec->decode(substr($bytes, 0, $mtiBytes), 4));
        } catch (ValidationException $e) {
            throw new ParseException(
                'The message does not begin with a valid MTI: ' . $e->getMessage(),
                previous: $e,
            );
        }


        $offset += $mtiBytes;


        // --------- MTI ----------------------------------------------------------------

        if ($total < $offset + 8) {
            throw new ParseException(
                sprintf(
                    'Truncated message: needed at least 8 bitmap byte(s) at offset %d, got %d',
                    $offset,
                    $total - $offset
                )
            );
        }

        // Bit 1 of the first bitmap byte decides whether 8 or 16 bytes follow.
        $bitmapLength = ((ord($bytes[$offset]) & 0x80) === 0x80) ? 16 : 8;

        if ($total < $offset + $bitmapLength) {
            throw new ParseException(
                sprintf(
                    'Truncated message: the bitmap declares %d byte(s) at offset %d, got %d.',
                    $bitmapLength,
                    $offset,
                    $total - $offset
                )
            );
        }


        // get the bitmap
        $bitmap = Bitmap::fromBinary(substr($bytes, $offset, $bitmapLength));
        $offset += $bitmapLength;


        // --- Fields -------------------------------------------------------------
        $fields = [];

        // loop through the presents fields 
        foreach ($bitmap->presentFields() as $number) {

            try {
                $definition = $version->field($number);
            } catch (ValidationException $e) {
                throw new ParseException(
                    sprintf(
                        'The bitmap declares field %d, which ISO 8583:%s does not define.',
                        $number,
                        $version->name(),
                    ), previous: $e
                );
            }

            $decoded = $definition->fieldType()->decode($bytes, $offset);

            $fields[$number] = $decoded->value;
            $offset += $decoded->bytesConsumed;
        }

        if ($offset !== $total) {
            throw new ParseException(
                sprintf(
                    '%d trailing byte(s) after the last field; the message and the catalogue disagree.',
                    $total - $offset,
                )
            );
        }

        return new self($mti, $version, $fields, $mtiEncoding);
    }


    public function __tostring(): string
    {
        return $this->toHex();
    }
}