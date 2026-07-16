<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583;

use Tuurosung\switch8583\Exceptions\ValidationException;

/**
 * A Message Type Indicator: the four digits that open every ISO 8583 message
 * and tell the receiver what kind of conversation it is having.
 *
 * Each position carries one fact:
 *
 *   Position 1 — version:  0 = 1987, 1 = 1993, 2 = 2003.
 *   Position 2 — class:    1 authorization, 2 financial, 3 file action,
 *                          4 reversal/chargeback, 5 reconciliation,
 *                          6 administrative, 7 fee collection,
 *                          8 network management.
 *   Position 3 — function: 0 request, 1 request response, 2 advice,
 *                          3 advice response, 4 notification,
 *                          5 notification acknowledgement, 6 instruction,
 *                          7 instruction acknowledgement.
 *   Position 4 — origin:   0 acquirer, 1 acquirer repeat, 2 issuer,
 *                          3 issuer repeat, 4 other, 5 other repeat.
 *
 * The function digit encodes the request/response pairing structurally: every
 * response is its request's function digit plus one, which is why 0100 pairs
 * with 0110, 0200 with 0210, 0420 with 0430, and 0800 with 0810.
 * {@see self::response()} derives the pair instead of asking callers to
 * memorise it.
 *
 * The object holds logical digits only. How an MTI reaches the wire (4 ASCII
 * bytes or 2 BCD bytes) is the message layer's concern, decided by the same
 * codec machinery as any field.
 */
final readonly class Mti implements \Stringable
{
    private function __construct(
        public string $value
    ){}


    public static function fromString(string $mti): self
    {
        if (strlen($mti) !== 4 || !ctype_digit($mti)) {
            throw new ValidationException(
                sprintf(
                    'An MTI value is exactly 4 digits; got "%s".',
                    $mti
                )
            );
        }

        return new self($mti);
    }


    public function versionDigit(): int
    {
        return (int) $this->value[0];
    }


    public function classDigit(): int
    {
        return (int) $this->value[1];
    }


    public function functionDigit(): int
    {
        return (int) $this->value[2];
    }


    public function originDigit(): int
    {
        return (int) $this->value[3];
    }


    /**
     * Whether this is an originating message awaiting an answer: a request (x0),
     * advice (x2), notification (x4), or instruction (x6).
     */
    public function isRequest(): bool
    {
        return ($this->functionDigit() % 2) === 0;
    }


    /**
     * Whether this answers another message: a request response (x1), advice
     * response (x3), notification acknowledgement (x5), or instruction
     * acknowledgement (x7).
     */
    public function isResponse(): bool
    {
        return !$this->isRequest();
    }


    /**
     * Whether the origin digit marks this as a repeat transmission.
     */
    public function isRepeat(): bool
    {
        return ($this->originDigit() % 2) === 1;
    }



    /**
     * The MTI that answers this one: the function digit incremented by one,
     * everything else preserved. 0100 -> 0110, 0200 -> 0210, 0420 -> 0430,
     * 0800 -> 0810, 1100 -> 1110.
     *
     * @throws ValidationException When called on a message that is already a
     *      response — 0110 has no answer of its own, so
     *      asking for one is a logic bug upstream.
     */
    public function response(): self
    {
        if ($this->isResponse()) {
            throw new ValidationException(
                sprintf(
                    'MTI %s is already a response; it has no response of its own',
                    $this->value
                )
            );
        }

        $answer = $this->value;
        $answer[2] = (string) ($this->functionDigit() + 1);

        return new self($answer);
    }


    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }


    public function __toString(): string
    {
        return $this->value;
    }
}