<?php

declare (strict_types = 1);

namespace Tuurosung\switch8583\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tuurosung\switch8583\Codec\BcdPadding;
use Tuurosung\switch8583\Definitions\FieldDefinition;
use Tuurosung\switch8583\Definitions\FieldEncoding;
use Tuurosung\switch8583\Definitions\Iso8583v1987;
use Tuurosung\switch8583\Message;


/**
 * The 0100/0110 authorization exchange against hand-derived wire fixtures.
 *
 * The fixtures below were assembled from the spec BY HAND — MTI, bitmap bytes
 * computed bit by bit, then each field's wire form — precisely so the tests
 * do not merely check that the library agrees with itself. The parser must
 * read bytes it did not produce; the builder must reproduce them identically.
 */
final class AuthorizationFlowTest extends TestCase
{
    /**
     * 0100 with fields {2,3,4,7,11,14,22,37,41,42,49}, ASCII profile.
     *
     * Bitmap, derived by hand:
     *   byte0 (f1-8):   f2 0x40 | f3 0x20 | f4 0x10 | f7 0x02      = 0x72
     *   byte1 (f9-16):  f11 0x20 | f14 0x04                        = 0x24
     *   byte2 (f17-24): f22 0x04                                   = 0x04
     *   byte4 (f33-40): f37 0x08                                   = 0x08
     *   byte5 (f41-48): f41 0x80 | f42 0x40                        = 0xC0
     *   byte6 (f49-56): f49 0x80                                   = 0x80
     */
    private const REQUEST_HEX =
        '30313030'                          // MTI '0100'
        . '7224040008C08000'                // primary bitmap
        . '3136'                            // F2  LLVAR prefix '16'
        . '34313131313131313131313131313131' // F2  PAN '4111111111111111'
        . '303030303030'                    // F3  '000000'
        . '303030303030303132353030'        // F4  '000000012500'
        . '30373135313433303232'            // F7  '0715143022'
        . '303030313233'                    // F11 '000123'
        . '32383132'                        // F14 '2812'
        . '303531'                          // F22 '051'
        . '524546313233343536373839'        // F37 'REF123456789'
        . '5445524D30303031'                // F41 'TERM0001'
        . '4B494E445245445445434830303031' // F42 'KINDREDTECH0001'
        . '393336';                          // F49 '936' (GHS)


    /**
     * The matching 0110: request fields minus F14/F22, plus F38/F39.
     *   byte4 gains f38 0x04 | f39 0x02 -> 0x0E; byte1 drops f14 -> 0x20;
     *   byte2 drops f22 -> 0x00.
     */
    private const RESPONSE_HEX =
        '30313130'                          // MTI '0110'
        . '722000000EC08000'                // primary bitmap
        . '3136'
        . '34313131313131313131313131313131'
        . '303030303030'
        . '303030303030303132353030'
        . '30373135313433303232'
        . '303030313233'
        . '524546313233343536373839'
        . '415554483031'                    // F38 'AUTH01'
        . '3030'                            // F39 '00' (approved)
        . '5445524D30303031'
        . '4B494E445245445445434830303031'
        . '393336';


    #[Test]
    public function it_parses_a_request_it_did_not_produce(): void
    {
        $request = Message::parse(hex2bin(self::REQUEST_HEX), new Iso8583v1987());

        self::assertSame('0100', $request->mti()->value);
        self::assertTrue($request->mti()->isRequest());
        self::assertSame(
            [2, 3, 4, 7, 11, 14, 22, 37, 41, 42, 49],
            array_keys($request->fields()),
        );
        self::assertSame('4111111111111111', $request->getField(2));
        self::assertSame('000000012500', $request->getField(4));
        self::assertSame('000123', $request->getField(11));
        self::assertSame('2812', $request->getField(14));
        self::assertSame('REF123456789', $request->getField(37));
        self::assertSame('KINDREDTECH0001', $request->getField(42));
        self::assertSame('936', $request->getField(49));
    }


    #[Test]
    public function the_builder_reproduces_the_fixture_byte_for_byte(): void
    {
        $request = Message::make('0100', new Iso8583v1987())
            ->withField(2, '4111111111111111')
            ->withField(3, '000000')
            ->withField(4, '000000012500')
            ->withField(7, '0715143022')
            ->withField(11, '000123')
            ->withField(14, '2812')
            ->withField(22, '051')
            ->withField(37, 'REF123456789')
            ->withField(41, 'TERM0001')
            ->withField(42, 'KINDREDTECH0001')
            ->withField(49, '936');

        self::assertSame(strtoupper(self::REQUEST_HEX), $request->toHex());
    }


    #[Test]
    public function a_parsed_request_transforms_into_the_expected_response(): void
    {
        $request = Message::parse(hex2bin(self::REQUEST_HEX), new Iso8583v1987());

        $response = $request
            ->withMti($request->mti()->response())
            ->withoutField(14)
            ->withoutField(22)
            ->withField(38, 'AUTH01')
            ->withField(39, '00');

        self::assertSame(strtoupper(self::RESPONSE_HEX), $response->toHex());
    }

    #[Test]
    public function the_response_fixture_parses_and_pairs_with_the_request(): void
    {
        $request = Message::parse(hex2bin(self::REQUEST_HEX), new Iso8583v1987());
        $response = Message::parse(hex2bin(self::RESPONSE_HEX), new Iso8583v1987());

        self::assertTrue($response->mti()->equals($request->mti()->response()));
        self::assertSame('00', $response->getField(39));
        // The STAN and RRN tie the pair together.
        self::assertSame($request->getField(11), $response->getField(11));
        self::assertSame($request->getField(37), $response->getField(37));
    }

    /**
     * A packed-network profile: BCD MTI (2 bytes) and a BCD PAN override.
     *
     * Fixture, by hand: MTI 0x01 0x00; bitmap {2,3} = 0x60...; F2 as BCD
     * prefix 0x16 + 8 packed bytes; F3 still ASCII from the stock catalogue.
     */
    #[Test]
    public function a_bcd_profile_fixture_parses_and_rebuilds(): void
    {
        $fixtureHex =
            '0100'                 // MTI, BCD-packed
            . '6000000000000000'   // bitmap {2,3}
            . '16'                 // F2 LLVAR prefix, BCD
            . '4111111111111111'   // F2 PAN, BCD-packed (8 bytes)
            . '303030303030';      // F3 '000000', ASCII

        $network = (new Iso8583v1987())->withField(
            FieldDefinition::llvar(2, 'Primary Account Number', 19, FieldEncoding::Bcd)
                ->withBcdPadding(BcdPadding::Right, 0xF),
        );

        $parsed = Message::parse(hex2bin($fixtureHex), $network, FieldEncoding::Bcd);

        self::assertSame('0100', $parsed->mti()->value);
        self::assertSame('4111111111111111', $parsed->getField(2));
        self::assertSame('000000', $parsed->getField(3));

        $rebuilt = Message::make('0100', $network, FieldEncoding::Bcd)
            ->withField(2, '4111111111111111')
            ->withField(3, '000000');

        self::assertSame(strtoupper($fixtureHex), $rebuilt->toHex());
    }
}