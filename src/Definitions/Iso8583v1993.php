<?php

declare(strict_types=1);

namespace Tuurosung\switch8583\Definitions;

use Tuurosung\switch8583\Definitions\FieldDefinition as F;
use Tuurosung\switch8583\Definitions\FieldEncoding as E;

/**
 * The ISO 8583:1993 field catalogue, in the same ASCII encoding profile as
 * {@see Iso8583v1987} (see that class's docblock for the profile contract and
 * the override recipe for BCD networks).
 *
 * The 1993 revision restructured a number of elements rather than merely
 * renumbering them. The deltas most likely to bite anyone porting 1987 code:
 *
 *   - F22 grows from n3 "POS Entry Mode" to an12 "Point of Service Data Code".
 *   - F24 becomes the n3 Function Code (the NII meaning is gone).
 *   - F25 becomes the n4 Message Reason Code; F26 the n4 Card Acceptor
 *     Business Code.
 *   - F28 becomes the n6 reconciliation date; the 1987 x+n fee amounts in
 *     F28..F31 are gone from those slots.
 *   - F39 grows from an2 to n3 (Action Code).
 *   - F43 becomes variable: ans..99 LLVAR, subfielded.
 *   - F53 becomes variable binary: b..48 LLVAR.
 *   - F55 is formally the ICC (EMV) data element: b..255 LLLVAR.
 *   - F56 carries Original Data Elements (n..35 LLVAR); 1987 kept them in F90.
 *   - F72 becomes the free-form Data Record (ans..999 LLLVAR).
 *   - F93/F94 become the transaction destination/originator institution IDs
 *     (n..11 LLVAR); F95 the Card Issuer Reference Data (ans..99 LLVAR);
 *     F96 the Key Management Data (b..999 LLLVAR).
 *
 * NOTE: the 1993 tables in the wild vary more than the 1987 ones, and this
 * catalogue should be pinned against captured fixtures (project step 8) and
 * the target network's spec sheet before being treated as gospel. Any
 * discrepancy is a one-line {@see AbstractVersion::withField()} override.
 */
final class Iso8583v1993 extends AbstractVersion
{
    public function name(): string
    {
        return '1993';
    }

    protected function catalogue(): array
    {
        return [
            F::llvar(2, 'Primary Account Number', 19, E::Ascii),                    // n..19
            F::fixed(3, 'Processing Code', 6, E::Ascii),                            // n6
            F::fixed(4, 'Amount, Transaction', 12, E::Ascii),                       // n12
            F::fixed(5, 'Amount, Reconciliation', 12, E::Ascii),                    // n12
            F::fixed(6, 'Amount, Cardholder Billing', 12, E::Ascii),                // n12
            F::fixed(7, 'Date and Time, Transmission', 10, E::Ascii),               // n10 MMDDhhmmss
            F::fixed(8, 'Amount, Cardholder Billing Fee', 8, E::Ascii),             // n8
            F::fixed(9, 'Conversion Rate, Reconciliation', 8, E::Ascii),            // n8
            F::fixed(10, 'Conversion Rate, Cardholder Billing', 8, E::Ascii),       // n8
            F::fixed(11, 'System Trace Audit Number', 6, E::Ascii),                 // n6
            F::fixed(12, 'Date and Time, Local Transaction', 12, E::Ascii),         // n12 YYMMDDhhmmss
            F::fixed(13, 'Date, Effective', 4, E::Ascii),                           // n4 YYMM
            F::fixed(14, 'Date, Expiration', 4, E::Ascii),                          // n4 YYMM
            F::fixed(15, 'Date, Settlement', 6, E::Ascii),                          // n6 YYMMDD
            F::fixed(16, 'Date, Conversion', 4, E::Ascii),                          // n4 MMDD
            F::fixed(17, 'Date, Capture', 4, E::Ascii),                             // n4 MMDD
            F::fixed(18, 'Merchant Type', 4, E::Ascii),                             // n4
            F::fixed(19, 'Country Code, Acquiring Institution', 3, E::Ascii),       // n3
            F::fixed(20, 'Country Code, Primary Account Number', 3, E::Ascii),      // n3
            F::fixed(21, 'Country Code, Forwarding Institution', 3, E::Ascii),      // n3
            F::fixed(22, 'Point of Service Data Code', 12, E::Ascii),               // an12
            F::fixed(23, 'Card Sequence Number', 3, E::Ascii),                      // n3
            F::fixed(24, 'Function Code', 3, E::Ascii),                             // n3
            F::fixed(25, 'Message Reason Code', 4, E::Ascii),                       // n4
            F::fixed(26, 'Card Acceptor Business Code', 4, E::Ascii),               // n4
            F::fixed(27, 'Approval Code Length', 1, E::Ascii),                      // n1
            F::fixed(28, 'Date, Reconciliation', 6, E::Ascii),                      // n6 YYMMDD
            F::fixed(29, 'Reconciliation Indicator', 3, E::Ascii),                  // n3
            F::fixed(30, 'Amounts, Original', 24, E::Ascii),                        // n24
            F::llvar(31, 'Acquirer Reference Data', 99, E::Ascii),                  // ans..99
            F::llvar(32, 'Acquiring Institution Identification Code', 11, E::Ascii), // n..11
            F::llvar(33, 'Forwarding Institution Identification Code', 11, E::Ascii), // n..11
            F::llvar(34, 'Primary Account Number, Extended', 28, E::Ascii),         // ns..28
            F::llvar(35, 'Track 2 Data', 37, E::Ascii),                             // z..37
            F::lllvar(36, 'Track 3 Data', 104, E::Ascii),                           // z..104
            F::fixed(37, 'Retrieval Reference Number', 12, E::Ascii),               // anp12
            F::fixed(38, 'Approval Code', 6, E::Ascii),                             // anp6
            F::fixed(39, 'Action Code', 3, E::Ascii),                               // n3
            F::fixed(40, 'Service Code', 3, E::Ascii),                              // n3
            F::fixed(41, 'Card Acceptor Terminal Identification', 8, E::Ascii),     // ans8
            F::fixed(42, 'Card Acceptor Identification Code', 15, E::Ascii),        // ans15
            F::llvar(43, 'Card Acceptor Name/Location', 99, E::Ascii),              // ans..99
            F::llvar(44, 'Additional Response Data', 99, E::Ascii),                 // ans..99
            F::llvar(45, 'Track 1 Data', 76, E::Ascii),                             // ans..76
            F::lllvar(46, 'Amounts, Fees', 204, E::Ascii),                          // ans..204
            F::lllvar(47, 'Additional Data, National', 999, E::Ascii),              // ans..999
            F::lllvar(48, 'Additional Data, Private', 999, E::Ascii),               // ans..999
            F::fixed(49, 'Currency Code, Transaction', 3, E::Ascii),                // n3
            F::fixed(50, 'Currency Code, Reconciliation', 3, E::Ascii),             // n3
            F::fixed(51, 'Currency Code, Cardholder Billing', 3, E::Ascii),         // n3
            F::fixed(52, 'Personal Identification Number (PIN) Data', 8, E::Binary), // b8 bytes
            F::llvar(53, 'Security Related Control Information', 48, E::Binary),    // b..48
            F::lllvar(54, 'Amounts, Additional', 120, E::Ascii),                    // ans..120
            F::lllvar(55, 'Integrated Circuit Card (ICC) Data', 255, E::Binary),    // b..255
            F::llvar(56, 'Original Data Elements', 35, E::Ascii),                   // n..35
            F::fixed(57, 'Authorization Life Cycle Code', 3, E::Ascii),             // n3
            F::llvar(58, 'Authorizing Agent Institution Identification Code', 11, E::Ascii), // n..11
            F::lllvar(59, 'Transport Data', 999, E::Ascii),                         // ans..999
            F::lllvar(60, 'Reserved for National Use', 999, E::Ascii),
            F::lllvar(61, 'Reserved for National Use', 999, E::Ascii),
            F::lllvar(62, 'Reserved for Private Use', 999, E::Ascii),
            F::lllvar(63, 'Reserved for Private Use', 999, E::Ascii),
            F::fixed(64, 'Message Authentication Code (MAC)', 8, E::Binary),        // b8 bytes
            F::fixed(65, 'Bitmap, Tertiary', 8, E::Binary),                         // reserved; tertiary parsing out of scope for v1
            F::fixed(66, 'Amounts, Original Fees', 204, E::Ascii),                  // ans..204 in some tables; fixed here pending fixture pass
            F::fixed(67, 'Extended Payment Data', 2, E::Ascii),                     // n2
            F::fixed(68, 'Country Code, Receiving Institution', 3, E::Ascii),       // n3
            F::fixed(69, 'Country Code, Settlement Institution', 3, E::Ascii),      // n3
            F::fixed(70, 'Country Code, Authorizing Agent Institution', 3, E::Ascii), // n3
            F::fixed(71, 'Message Number', 8, E::Ascii),                            // n8
            F::lllvar(72, 'Data Record', 999, E::Ascii),                            // ans..999
            F::fixed(73, 'Date, Action', 6, E::Ascii),                              // n6 YYMMDD
            F::fixed(74, 'Credits, Number', 10, E::Ascii),                          // n10
            F::fixed(75, 'Credits, Reversal Number', 10, E::Ascii),                 // n10
            F::fixed(76, 'Debits, Number', 10, E::Ascii),                           // n10
            F::fixed(77, 'Debits, Reversal Number', 10, E::Ascii),                  // n10
            F::fixed(78, 'Transfer, Number', 10, E::Ascii),                         // n10
            F::fixed(79, 'Transfer, Reversal Number', 10, E::Ascii),                // n10
            F::fixed(80, 'Inquiries, Number', 10, E::Ascii),                        // n10
            F::fixed(81, 'Authorizations, Number', 10, E::Ascii),                   // n10
            F::fixed(82, 'Inquiries, Reversal Number', 10, E::Ascii),               // n10
            F::fixed(83, 'Payments, Number', 10, E::Ascii),                         // n10
            F::fixed(84, 'Payments, Reversal Number', 10, E::Ascii),                // n10
            F::fixed(85, 'Fee Collections, Number', 10, E::Ascii),                  // n10
            F::fixed(86, 'Credits, Amount', 16, E::Ascii),                          // n16
            F::fixed(87, 'Credits, Reversal Amount', 16, E::Ascii),                 // n16
            F::fixed(88, 'Debits, Amount', 16, E::Ascii),                           // n16
            F::fixed(89, 'Debits, Reversal Amount', 16, E::Ascii),                  // n16
            F::fixed(90, 'Authorizations, Reversal Number', 10, E::Ascii),          // n10
            F::fixed(91, 'Country Code, Transaction Destination Institution', 3, E::Ascii), // n3
            F::fixed(92, 'Country Code, Transaction Originator Institution', 3, E::Ascii),  // n3
            F::llvar(93, 'Transaction Destination Institution Identification Code', 11, E::Ascii), // n..11
            F::llvar(94, 'Transaction Originator Institution Identification Code', 11, E::Ascii),  // n..11
            F::llvar(95, 'Card Issuer Reference Data', 99, E::Ascii),               // ans..99
            F::lllvar(96, 'Key Management Data', 999, E::Binary),                   // b..999
            F::fixed(97, 'Amount, Net Reconciliation', 17, E::Ascii),               // x+n16
            F::fixed(98, 'Payee', 25, E::Ascii),                                    // ans25
            F::llvar(99, 'Settlement Institution Identification Code', 11, E::Ascii),  // an..11
            F::llvar(100, 'Receiving Institution Identification Code', 11, E::Ascii),  // n..11
            F::llvar(101, 'File Name', 17, E::Ascii),                               // ans..17
            F::llvar(102, 'Account Identification 1', 28, E::Ascii),                // ans..28
            F::llvar(103, 'Account Identification 2', 28, E::Ascii),                // ans..28
            F::lllvar(104, 'Transaction Description', 100, E::Ascii),               // ans..100
            F::lllvar(105, 'Credits, Chargeback Amount', 16, E::Ascii),             // n16 in some tables; kept variable-safe pending fixture pass
            F::lllvar(106, 'Debits, Chargeback Amount', 16, E::Ascii),
            F::lllvar(107, 'Credits, Chargeback Number', 10, E::Ascii),
            F::lllvar(108, 'Debits, Chargeback Number', 10, E::Ascii),
            F::lllvar(109, 'Credits, Fee Amounts', 84, E::Ascii),                   // ans..84
            F::lllvar(110, 'Debits, Fee Amounts', 84, E::Ascii),                    // ans..84
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
            F::fixed(128, 'Message Authentication Code (MAC) 2', 8, E::Binary),     // b8 bytes
        ];
    }
}
