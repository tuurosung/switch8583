<?php

declare (strict_types = 1);
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Definitions\Iso8583v1987;
use Tuurosung\switch8583\Message;



/**
 * The 0420/0430 reversal advice exchange and the 0800/0810 echo test —
 * both crossing into the secondary bitmap (F90, F70), hand-derived fixtures.
 */
final class ReversalAndNetworkFlowTest extends TestCase
{
    /**
     * 0420 with fields {2,3,4,7,11,37,41,49,90}. F90 forces the dual bitmap.
     *
     * Bitmap, by hand:
     *   byte0:  secondary 0x80 | f2 0x40 | f3 0x20 | f4 0x10 | f7 0x02 = 0xF2
     *   byte1:  f11 0x20
     *   byte4:  f37 0x08
     *   byte5:  f41 0x80
     *   byte6:  f49 0x80
     *   byte11: f90 0x40   ((90-1)>>3 = 11, (90-1)%8 = 1 -> 0x80>>1)
     *
     * F90 Original Data Elements, n42:
     *   original MTI '0200' + STAN '000777' + transmission '0715150100'
     *   + acquiring id '00000000001' + forwarding id '00000000000'.
     */
    private const REVERSAL_HEX =
    '30343230'                              // MTI '0420'
        . 'F220000008808000'                    // primary bitmap
        . '0000004000000000'                    // secondary bitmap
        . '3136'                                // F2 LLVAR prefix '16'
        . '34313131313131313131313131313131'    // F2 '4111111111111111'
        . '303030303030'                        // F3 '000000'
        . '303030303030303530303030'            // F4 '000000050000'
        . '30373135313531353030'                // F7 '0715151500'
        . '303030373738'                        // F11 '000778' (the reversal's own STAN)
        . '524546313233343536373839'            // F37 'REF123456789'
        . '5445524D30303031'                    // F41 'TERM0001'
        . '393336'                              // F49 '936'
        . '303230303030303737373037313531353031303030303030303030303030313030303030303030303030'; // F90 (42n)

    #[Test]
    public function it_parses_the_reversal_and_exposes_the_original_transaction(): void
    {
        $reversal = Message::parse(hex2bin(self::REVERSAL_HEX), new Iso8583v1987());

        self::assertSame('0420', $reversal->mti()->value);
        self::assertTrue($reversal->bitmap()->hasSecondaryBitmap());

        $original = $reversal->getField(90);

        self::assertSame(42, strlen($original));
        self::assertSame('0200', substr($original, 0, 4));    // original MTI
        self::assertSame('000777', substr($original, 4, 6));  // original STAN
    }

    #[Test]
    public function the_builder_reproduces_the_reversal_fixture(): void
    {
        $reversal = Message::make('0420', new Iso8583v1987())
            ->withField(2, '4111111111111111')
            ->withField(3, '000000')
            ->withField(4, '000000050000')
            ->withField(7, '0715151500')
            ->withField(11, '000778')
            ->withField(37, 'REF123456789')
            ->withField(41, 'TERM0001')
            ->withField(49, '936')
            ->withField(90, '020000077707151501000000000000100000000000');

        self::assertSame(strtoupper(self::REVERSAL_HEX), $reversal->toHex());
    }

    #[Test]
    public function the_reversal_response_drops_back_to_a_primary_bitmap(): void
    {
        $reversal = Message::parse(hex2bin(self::REVERSAL_HEX), new Iso8583v1987());

        $response = $reversal
            ->withMti($reversal->mti()->response())
            ->withoutField(90)
            ->withField(39, '00');

        self::assertSame('0430', $response->mti()->value);
        self::assertFalse($response->bitmap()->hasSecondaryBitmap());

        // And the wire shrinks accordingly: 8 bitmap bytes, not 16.
        $roundTripped = Message::parse($response->toBytes(), new Iso8583v1987());
        self::assertSame('00', $roundTripped->getField(39));
    }

    /**
     * The 0800 echo test: fields {7,11,70}, F70 '301' (echo).
     *
     *   byte0: secondary 0x80 | f7 0x02 = 0x82
     *   byte1: f11 0x20
     *   byte8: f70 0x04   ((70-1)>>3 = 8, (69)%8 = 5 -> 0x80>>5)
     */
    private const ECHO_HEX =
    '30383030'                          // MTI '0800'
        . '8220000000000000'                // primary bitmap
        . '0400000000000000'                // secondary bitmap
        . '30373135313433303232'            // F7  '0715143022'
        . '303030313233'                    // F11 '000123'
        . '333031';                          // F70 '301'

    #[Test]
    public function the_echo_test_parses_and_answers(): void
    {
        $echo = Message::parse(hex2bin(self::ECHO_HEX), new Iso8583v1987());

        self::assertSame('0800', $echo->mti()->value);
        self::assertSame('301', $echo->getField(70));

        $answer = $echo
            ->withMti($echo->mti()->response())
            ->withField(39, '00');

        self::assertSame('0810', $answer->mti()->value);

        $parsed = Message::parse($answer->toBytes(), new Iso8583v1987());

        self::assertSame('00', $parsed->getField(39));
        self::assertSame('301', $parsed->getField(70));   // Echoed back.
        self::assertSame($echo->getField(11), $parsed->getField(11));
    }

    #[Test]
    public function the_builder_reproduces_the_echo_fixture(): void
    {
        $echo = Message::make('0800', new Iso8583v1987())
            ->withField(7, '0715143022')
            ->withField(11, '000123')
            ->withField(70, '301');

        self::assertSame(strtoupper(self::ECHO_HEX), $echo->toHex());
    }
}