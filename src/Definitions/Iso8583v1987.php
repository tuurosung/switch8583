<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Definitions;

use Override;
use Tuurosung\switch8583\Definitions\FieldDefinition as F;
use Tuurosung\switch8583\Definitions\FieldEncoding as E;

/**
 * The ISO 8583:1987 field catalogue.
 *
 * ENCODING PROFILE: this catalogue is the ASCII profile — every character
 * field maps one byte per character, and "b" (binary) fields carry raw bytes
 * whose length counts bytes. It is the most interoperable, most debuggable
 * default. Networks that pack numerics as BCD (very common for the PAN,
 * amounts, and STAN) are expressed by overriding the affected fields:
 *
 *     $network = (new Iso8583v1987())->withField(
 *         F::llvar(2, 'Primary Account Number', 19, E::Bcd)
 *             ->withBcdPadding(BcdPadding::Right, 0xF),
 *         F::fixed(4, 'Amount, Transaction', 12, E::Bcd),
 *     );
 *
 * Notation from the spec tables, for cross-checking: n = numeric, an =
 * alphanumeric, ans = alphanumeric + special, z = track data, b = binary,
 * x+n = a C/D sign character followed by n digits (carried here as a fixed
 * ASCII field one character longer). ".." marks variable length.
 */
final class Iso8583v1987 extends AbstractVersion
{

    public function name(): string
    {
        return '1987';
    }


    #[Override]
    protected function catalogue(): array
    {
        return [
            F::llvar(2, 'Primary Account Number', 19, E::Ascii),                    // n..19
            F::fixed(3, 'Processing Code', 6, E::Ascii),                             // n6
            F::fixed(4, 'Amount, Transaction', 12, E::Ascii),                         // n12
            F::fixed(5, 'Amount, Settlement', 12, E::Ascii),                         // n12
            F::fixed(6, 'Amount, Cardholder Billing', 12, E::Ascii),                 // n12
            F::fixed(7, 'Transmission Date and Time', 10, E::Ascii),                // n10 MMDDhhmmss
            F::fixed(8, 'Amount, Cardholder Billing Fee', 8, E::Ascii),             // n8
            F::fixed(9, 'Conversion Rate, Settlement', 8, E::Ascii),                // n8
            F::fixed(10, 'Conversion Rate, Cardholder Billing', 8, E::Ascii),       // n8
            F::fixed(11, 'System Trace Audit Number', 6, E::Ascii),                 // n6
            F::fixed(12, 'Time, Local Transaction', 6, E::Ascii),                   // n6 hhmmss
            F::fixed(13, 'Date, Local Transaction', 4, E::Ascii),                   // n4 MMDD
            F::fixed(14, 'Date, Expiration', 4, E::Ascii),                          // n4 YYMM
            F::fixed(15, 'Date, Settlement', 4, E::Ascii),                          // n4
            F::fixed(16, 'Date, Conversion', 4, E::Ascii),                          // n4
            F::fixed(17, 'Date, Capture', 4, E::Ascii),                             // n4
            F::fixed(18, 'Merchant Type', 4, E::Ascii),                             // n4
            F::fixed(19, 'Acquiring Institution Country Code', 3, E::Ascii),        // n3
            F::fixed(20, 'PAN Extended Country Code', 3, E::Ascii),                 // n3
            F::fixed(21, 'Forwarding Institution Country Code', 3, E::Ascii),       // n3
            F::fixed(22, 'Point of Service Entry Mode', 3, E::Ascii),               // n3
            F::fixed(23, 'Card Sequence Number', 3, E::Ascii),                      // n3
            F::fixed(24, 'Network International Identifier', 3, E::Ascii),          // n3
            F::fixed(25, 'Point of Service Condition Code', 2, E::Ascii),           // n2
            F::fixed(26, 'Point of Service Capture Code', 2, E::Ascii),             // n2
            F::fixed(27, 'Authorizing Identification Response Length', 1, E::Ascii), // n1
            F::fixed(28, 'Amount, Transaction Fee', 9, E::Ascii),                   // x+n8
            F::fixed(29, 'Amount, Settlement Fee', 9, E::Ascii),                    // x+n8
            F::fixed(30, 'Amount, Transaction Processing Fee', 9, E::Ascii),        // x+n8
            F::fixed(31, 'Amount, Settlement Processing Fee', 9, E::Ascii),         // x+n8
            F::llvar(32, 'Acquiring Institution Identification Code', 11, E::Ascii), // n..11
            F::llvar(33, 'Forwarding Institution Identification Code', 11, E::Ascii), // n..11
            F::llvar(34, 'Primary Account Number, Extended', 28, E::Ascii),         // ns..28
            F::llvar(35, 'Track 2 Data', 37, E::Ascii),                             // z..37
            F::lllvar(36, 'Track 3 Data', 104, E::Ascii),                           // n..104
            F::fixed(37, 'Retrieval Reference Number', 12, E::Ascii),               // an12
            F::fixed(38, 'Authorization Identification Response', 6, E::Ascii),     // an6
            F::fixed(39, 'Response Code', 2, E::Ascii),                             // an2
            F::fixed(40, 'Service Restriction Code', 3, E::Ascii),                  // an3
            F::fixed(41, 'Card Acceptor Terminal Identification', 8, E::Ascii),     // ans8
            F::fixed(42, 'Card Acceptor Identification Code', 15, E::Ascii),        // ans15
            F::fixed(43, 'Card Acceptor Name/Location', 40, E::Ascii),              // ans40
            F::llvar(44, 'Additional Response Data', 25, E::Ascii),                 // an..25
            F::llvar(45, 'Track 1 Data', 76, E::Ascii),                             // an..76
            F::lllvar(46, 'Additional Data (ISO)', 999, E::Ascii),                  // an..999
            F::lllvar(47, 'Additional Data (National)', 999, E::Ascii),             // an..999
            F::lllvar(48, 'Additional Data (Private)', 999, E::Ascii),              // an..999
            F::fixed(49, 'Currency Code, Transaction', 3, E::Ascii),                // n3
            F::fixed(50, 'Currency Code, Settlement', 3, E::Ascii),                 // n3
            F::fixed(51, 'Currency Code, Cardholder Billing', 3, E::Ascii),         // n3
            F::fixed(52, 'Personal Identification Number (PIN) Data', 8, E::Binary), // b64
            F::fixed(53, 'Security Related Control Information', 16, E::Ascii),     // n16
            F::lllvar(54, 'Additional Amounts', 120, E::Ascii),                     // an..120
            F::lllvar(55, 'Integrated Circuit Card (ICC) Data', 999, E::Binary),    // Reserved (ISO); carries EMV in practice
            F::lllvar(56, 'Reserved (ISO)', 999, E::Ascii),                         // ans..999
            F::lllvar(57, 'Reserved (National)', 999, E::Ascii),                    // ans..999
            F::lllvar(58, 'Reserved (National)', 999, E::Ascii),                    // ans..999
            F::lllvar(59, 'Reserved (National)', 999, E::Ascii),                    // ans..999
            F::lllvar(60, 'Reserved (National)', 999, E::Ascii),                    // ans..999
            F::lllvar(61, 'Reserved (Private)', 999, E::Ascii),                     // ans..999
            F::lllvar(62, 'Reserved (Private)', 999, E::Ascii),                     // ans..999
            F::lllvar(63, 'Reserved (Private)', 999, E::Ascii),                     // ans..999
            F::fixed(64, 'Message Authentication Code (MAC)', 8, E::Binary),        // b64
            F::fixed(65, 'Bitmap, Tertiary', 8, E::Binary),                         // b64; tertiary parsing is out of scope for v1
            F::fixed(66, 'Settlement Code', 1, E::Ascii),                           // n1
            F::fixed(67, 'Extended Payment Code', 2, E::Ascii),                     // n2
            F::fixed(68, 'Receiving Institution Country Code', 3, E::Ascii),        // n3
            F::fixed(69, 'Settlement Institution Country Code', 3, E::Ascii),       // n3
            F::fixed(70, 'Network Management Information Code', 3, E::Ascii),       // n3
            F::fixed(71, 'Message Number', 4, E::Ascii),                            // n4
            F::fixed(72, 'Message Number, Last', 4, E::Ascii),                      // n4
            F::fixed(73, 'Date, Action', 6, E::Ascii),                              // n6 YYMMDD
            F::fixed(74, 'Credits, Number', 10, E::Ascii),                          // n10
            F::fixed(75, 'Credits, Reversal Number', 10, E::Ascii),                 // n10
            F::fixed(76, 'Debits, Number', 10, E::Ascii),                           // n10
            F::fixed(77, 'Debits, Reversal Number', 10, E::Ascii),                  // n10
            F::fixed(78, 'Transfer, Number', 10, E::Ascii),                         // n10
            F::fixed(79, 'Transfer, Reversal Number', 10, E::Ascii),                // n10
            F::fixed(80, 'Inquiries, Number', 10, E::Ascii),                        // n10
            F::fixed(81, 'Authorizations, Number', 10, E::Ascii),                   // n10
            F::fixed(82, 'Credits, Processing Fee Amount', 12, E::Ascii),           // n12
            F::fixed(83, 'Credits, Transaction Fee Amount', 12, E::Ascii),          // n12
            F::fixed(84, 'Debits, Processing Fee Amount', 12, E::Ascii),            // n12
            F::fixed(85, 'Debits, Transaction Fee Amount', 12, E::Ascii),           // n12
            F::fixed(86, 'Credits, Amount', 16, E::Ascii),                          // n16
            F::fixed(87, 'Credits, Reversal Amount', 16, E::Ascii),                 // n16
            F::fixed(88, 'Debits, Amount', 16, E::Ascii),                           // n16
            F::fixed(89, 'Debits, Reversal Amount', 16, E::Ascii),                  // n16
            F::fixed(90, 'Original Data Elements', 42, E::Ascii),                   // n42
            F::fixed(91, 'File Update Code', 1, E::Ascii),                          // an1
            F::fixed(92, 'File Security Code', 2, E::Ascii),                        // an2
            F::fixed(93, 'Response Indicator', 5, E::Ascii),                        // an5
            F::fixed(94, 'Service Indicator', 7, E::Ascii),                         // an7
            F::fixed(95, 'Replacement Amounts', 42, E::Ascii),                      // an42
            F::fixed(96, 'Message Security Code', 8, E::Binary),                    // b64
            F::fixed(97, 'Amount, Net Settlement', 17, E::Ascii),                   // x+n16
            F::fixed(98, 'Payee', 25, E::Ascii),                                    // ans25
            F::llvar(99, 'Settlement Institution Identification Code', 11, E::Ascii),  // n..11
            F::llvar(100, 'Receiving Institution Identification Code', 11, E::Ascii),  // n..11
            F::llvar(101, 'File Name', 17, E::Ascii),                               // ans..17
            F::llvar(102, 'Account Identification 1', 28, E::Ascii),                // ans..28
            F::llvar(103, 'Account Identification 2', 28, E::Ascii),                // ans..28
            F::lllvar(104, 'Transaction Description', 100, E::Ascii),               // ans..100
            F::lllvar(105, 'Reserved for ISO Use', 999, E::Ascii),
            F::lllvar(106, 'Reserved for ISO Use', 999, E::Ascii),
            F::lllvar(107, 'Reserved for ISO Use', 999, E::Ascii),
            F::lllvar(108, 'Reserved for ISO Use', 999, E::Ascii),
            F::lllvar(109, 'Reserved for ISO Use', 999, E::Ascii),
            F::lllvar(110, 'Reserved for ISO Use', 999, E::Ascii),
            F::lllvar(111, 'Reserved for ISO Use', 999, E::Ascii),
            F::lllvar(112, 'Reserved for National Use', 999, E::Ascii),
            F::lllvar(113, 'Reserved for National Use', 999, E::Ascii),
            F::lllvar(114, 'Reserved for National Use', 999, E::Ascii),
            F::lllvar(115, 'Reserved for National Use', 999, E::Ascii),
            F::lllvar(116, 'Reserved for National Use', 999, E::Ascii),
            F::lllvar(117, 'Reserved for National Use', 999, E::Ascii),
            F::lllvar(118, 'Reserved for National Use', 999, E::Ascii),
            F::lllvar(119, 'Reserved for National Use', 999, E::Ascii),
            F::lllvar(120, 'Reserved for Private Use', 999, E::Ascii),
            F::lllvar(121, 'Reserved for Private Use', 999, E::Ascii),
            F::lllvar(122, 'Reserved for Private Use', 999, E::Ascii),
            F::lllvar(123, 'Reserved for Private Use', 999, E::Ascii),
            F::lllvar(124, 'Reserved for Private Use', 999, E::Ascii),
            F::lllvar(125, 'Reserved for Private Use', 999, E::Ascii),
            F::lllvar(126, 'Reserved for Private Use', 999, E::Ascii),
            F::lllvar(127, 'Reserved for Private Use', 999, E::Ascii),
            F::fixed(128, 'Message Authentication Code (MAC) 2', 8, E::Binary),     // b64
        ];
    }
}