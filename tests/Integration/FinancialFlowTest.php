<?php

declare (strict_types = 1);

namespace Tuurosung\switch8583\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Definitions\Iso8583v1987;
use Tuurosung\switch8583\Message;


/**
 * The 0200/0210 financial exchange, hand-derived fixtures, ASCII profile.
 *
 * Adds Track 2 data (F35, LLVAR at its 37-character maximum, '=' separator)
 * to exercise a variable field with special characters mid-message.
 */
final class FinancialFlowTest extends TestCase
{
    /**
     * 0200 with fields {3,4,7,11,22,25,35,41,42,49}.
     *
     * Bitmap, by hand:
     *   byte0: f3 0x20 | f4 0x10 | f7 0x02 = 0x32
     *   byte1: f11 0x20                     = 0x20
     *   byte2: f22 0x04                     = 0x04
     *   byte3: f25 0x80                     = 0x80
     *   byte4: f35 0x20                     = 0x20
     *   byte5: f41 0x80 | f42 0x40          = 0xC0
     *   byte6: f49 0x80                     = 0x80
     */
    private const REQUEST_HEX =
        '30323030'                              // MTI '0200'
        . '3220048020C08000'                    // primary bitmap
        . '303030303030'                        // F3  '000000' (purchase)
        . '303030303030303530303030'            // F4  '000000050000' (GHS 500.00)
        . '30373135313530313030'                // F7  '0715150100'
        . '303030373737'                        // F11 '000777'
        . '303531'                              // F22 '051'
        . '3030'                                // F25 '00'
        . '3337'                                // F35 LLVAR prefix '37'
        . '34313131313131313131313131313131'    // F35 '4111111111111111'
        . '3D'                                  //     '='
        . '3238313231303130303030303132333030303030' // '28121010000012300000'
        . '5445524D30303031'                    // F41 'TERM0001'
        . '4B494E445245445445434830303031'    // F42 'KINDREDTECH0001'
        . '393336';                              // F49 '936'


    /**
     * 0210: request minus F35, plus F39 '00'.
     *   byte4 becomes f39 0x02 (f35 dropped).
     */
    private const RESPONSE_HEX =
        '30323130'
        . '3220048002C08000'
        . '303030303030'
        . '303030303030303530303030'
        . '30373135313530313030'
        . '303030373737'
        . '303531'
        . '3030'
        . '3030'                                // F39 '00'
        . '5445524D30303031'
        . '4B494E445245445445434830303031'
        . '393336';


    #[Test]
    public function it_parses_the_financial_request_including_track_2(): void
    {
        $request = Message::parse(hex2bin(self::REQUEST_HEX), new Iso8583v1987());

        self::assertSame('0200', $request->mti()->value);
        self::assertSame(
            [3, 4, 7, 11, 22, 25, 35, 41, 42, 49],
            array_keys($request->fields()),
        );
        self::assertSame('000000050000', $request->getField(4));
        self::assertSame('4111111111111111=28121010000012300000', $request->getField(35));
        self::assertSame('000777', $request->getField(11));
    }

    #[Test]
    public function the_builder_reproduces_the_fixture(): void
    {
        $request = Message::make('0200', new Iso8583v1987())
            ->withField(3, '000000')
            ->withField(4, '000000050000')
            ->withField(7, '0715150100')
            ->withField(11, '000777')
            ->withField(22, '051')
            ->withField(25, '00')
            ->withField(35, '4111111111111111=28121010000012300000')
            ->withField(41, 'TERM0001')
            ->withField(42, 'KINDREDTECH0001')
            ->withField(49, '936');

        self::assertSame(strtoupper(self::REQUEST_HEX), $request->toHex());
    }

    #[Test]
    public function the_approval_transform_reproduces_the_response_fixture(): void
    {
        $request = Message::parse(hex2bin(self::REQUEST_HEX), new Iso8583v1987());

        $response = $request
            ->withMti($request->mti()->response())
            ->withoutField(35)          // Track data never returns.
            ->withField(39, '00');

        self::assertSame(strtoupper(self::RESPONSE_HEX), $response->toHex());
    }

    #[Test]
    public function the_response_fixture_parses_as_an_approval(): void
    {
        $response = Message::parse(hex2bin(self::RESPONSE_HEX), new Iso8583v1987());

        self::assertSame('0210', $response->mti()->value);
        self::assertTrue($response->mti()->isResponse());
        self::assertSame('00', $response->getField(39));
        self::assertFalse($response->hasField(35));
    }
}