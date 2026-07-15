<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Definitions;

use Tuurosung\switch8583\Definitions\FieldDefinition;



/**
 * A complete catalogue of field definitions for one revision of the standard.
 *
 * The message layer speaks to versions only through this contract, so a
 * consumer can supply a fully custom catalogue — a proprietary network spec,
 * a test double — without touching the built-in ones.
 */
interface VersionInterface
{

    /**
     * The revision label, e.g. "1987" or "1993".
     */
    public function name(): string;


    /**
     * Whether this version defines the given field number.
     */
    public function hasField(int $number): bool;


    /**
     * The definition for one field.
     *
     * @throws \Tuurosung\switch8583\Exceptions\ValidationException
     *         When this version does not define the field.
     */
    public function field(int $number): FieldDefinition;



    /**
     * Every definition in the catalogue, keyed and sorted by field number.
     *
     * @return array<int, FieldDefinition>
     */
    public function fields(): array;
}