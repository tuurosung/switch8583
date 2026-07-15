<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Definitions;

use Tuurosung\switch8583\Exceptions\ValidationException;

/**
 * Shared machinery for the built-in version catalogues.
 *
 * Subclasses supply the raw catalogue; this class resolves it lazily, indexes
 * it by field number, and layers per-field overrides on top.
 *
 * Overrides are the extension seam for the real world: networks deviate from
 * the standard tables constantly — a BCD PAN here, a longer private field
 * there — and {@see self::withField()} makes each deviation a one-line,
 * immutable adjustment instead of a fork of the whole catalogue:
 *
 *     $network = (new Iso8583v1987())->withField(
 *         FieldDefinition::llvar(2, 'PAN', 19, FieldEncoding::Bcd)
 *             ->withBcdPadding(BcdPadding::Right, 0xF),
 *     );
 */
abstract class AbstractVersion implements VersionInterface
{
    /** @var array<int, FieldDefinition> */
    private array $overrides = [];


    /** @var array<int, FieldDefinition>|null */
    private ?array $resolved = null;


    /**
     * The raw catalogue for this revision. Field numbers must be unique.
     *
     * @return list<FieldDefinition>
     */
    abstract protected function catalogue(): array;


    public function withField(FieldDefinition ...$definitions): static
    {
        $clone = clone $this;
        $clone->resolved = null;

        foreach ($definitions as $definition) {
            $clone->overrides[$definition->number] = $definition;
        }

        return $clone;
    }

    
    public function hasField(int $number): bool
    {
        return isset($this->fields()[$number]);
    }


    public function field(int $number): FieldDefinition
    {
        $fields = $this->fields();

        if (!isset($fields[$number])) {
            throw new ValidationException(
                sprintf(
                    'Field %d is not defined in ISO 8583:%s',
                    $number,
                    $this->name()
                )
            );
        }

        return $fields[$number];
    }


    public function fields(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $base = [];

        foreach ($this->catalogue() as $definition) {
            if (isset($base[$definition->number])) {
                throw new ValidationException(
                    sprintf(
                        'The ISO 8583:%s catalogue defines field %d twice; the catalogue is buggy',
                        $this->name(),
                        $definition->number
                    )
                );
            }

            $base[$definition->number] = $definition;
        }

        $merged = array_replace($base, $this->overrides);
        ksort($merged);

        return $this->resolved = $merged;
    }
}