# switch8583

A clean, well-tested ISO 8583 message library for PHP.

Construction, parsing, and validation for the **1987** and **1993** versions of the standard, with correct dual-bitmap handling, BCD and ASCII encoding, and fixed / LLVAR / LLLVAR fields. Zero runtime dependencies. Framework-agnostic, with an optional thin Laravel adapter (A Special Addition For my Laravel Family).

```
composer require tuurosung/switch8583
```

Requires PHP 8.1+ and the `mbstring` extension.

---

## Why this exists

The PHP ecosystem has no robust, well-maintained, standards-complete ISO 8583 library. Existing options are abandoned, tied to a single gateway, or quietly wrong about the details that actually bite, such as odd-length BCD padding, length prefixes that count characters rather than bytes, the secondary-bitmap boundary. This library is built from the spec, verified against hand-derived wire fixtures, and strict where silence would corrupt data.

## Quick start

Build a financial request, put it on the wire, read it back:

```php
use Tuurosung\switch8583\Message;
use Tuurosung\switch8583\Definitions\Iso8583v1987;

$request = Message::make('0200', new Iso8583v1987())
    ->withField(3, '000000')            // Processing code (purchase)
    ->withField(4, '000000012500')      // Amount: 125.00
    ->withField(11, '000001')           // System trace audit number
    ->withField(41, 'TERM0001')         // Terminal ID
    ->withField(49, '936');             // Currency (GHS)

$raw = $request->toBytes();             // binary, ready for a socket
$hex = $request->toHex();               // uppercase hex, ready for a log

$parsed = Message::parse($raw, new Iso8583v1987());
$parsed->getField(4);                   // '000000012500'
```

Messages are immutable. Every `withField()` returns a new message; the bitmap is derived from the present fields at serialisation time and can never disagree with them.

## Building a response from a request

The MTI knows its own response, and a parsed request transforms straight into its reply:

```php
$request = Message::parse($raw, new Iso8583v1987());

$response = $request
    ->withMti($request->mti()->response())   // 0200 -> 0210
    ->withoutField(35)                        // track data never returns
    ->withField(39, '00');                    // approved

$response->mti()->value;                      // '0210'
```

`Mti::response()` derives the pair structurally (`0100 -> 0110`, `0420 -> 0430`, `0800 -> 0810`), so there is no lookup table to remember. Calling it on a message that is already a response throws, because that is a logic error upstream.

## Encoding profiles and network overrides

The built-in catalogues use the **ASCII profile** by default — the most interoperable, most debuggable baseline. Real networks deviate, and each deviation is a one-line, immutable override rather than a fork.

A network that packs the PAN as BCD with the trailing-`F` convention:

```php
use Tuurosung\switch8583\Definitions\FieldDefinition;
use Tuurosung\switch8583\Definitions\FieldEncoding;
use Tuurosung\switch8583\Codec\BcdPadding;

$network = (new Iso8583v1987())->withField(
    FieldDefinition::llvar(2, 'Primary Account Number', 19, FieldEncoding::Bcd)
        ->withBcdPadding(BcdPadding::Right, 0xF),
);

$message = Message::make('0100', $network)
    ->withField(2, '4111111111111111112')     // 19-digit PAN
    ->withField(3, '000000');

// F2 on the wire: prefix 0x19, ten packed bytes, last nibble the 0xF pad.
```

A network that packs the MTI itself into two BCD bytes:

```php
$message = Message::make('0200', new Iso8583v1987(), FieldEncoding::Bcd);
// The MTI now occupies 2 bytes instead of 4; field encodings still
// come from the catalogue, per field.
```

## Laravel (optional)

The library needs no framework. If you are on Laravel, the adapter is auto-discovered — publish the config and resolve a pre-configured factory from the container:

```
php artisan vendor:publish --tag=iso8583-config
```

```php
// config/iso8583.php -> version, mti_encoding
use Tuurosung\switch8583\Iso8583Factory;

$factory = app(Iso8583Factory::class);
$request = $factory->make('0200')->withField(3, '000000');
$parsed  = $factory->parse($raw);
```

`illuminate/support` is a *suggested* dependency, never required. Consumers outside Laravel never load a single framework file, and can construct `Iso8583Factory::for('1993', 'bcd')` directly.

## What's supported

| Area | Detail |
| --- | --- |
| Versions | ISO 8583:1987 and :1993, all 127 data elements each |
| Bitmaps | Primary and secondary (fields 2–128), binary and hex |
| Field formats | Fixed, LLVAR (2-digit prefix), LLLVAR (3-digit prefix) |
| Encoding | BCD (configurable padding side and nibble) and ASCII; independent data and length-prefix codecs |
| MTI | Semantic accessors, request/response derivation, repeat detection |
| Parsing | Strict: truncation, bitmap/indicator mismatch, unknown fields, and trailing bytes are all rejected |

## Design at a glance

A message is assembled through a single contract chain:

```
Message -> Bitmap
        -> FieldDefinition -> FieldType (Fixed | Llvar | Lllvar) -> Codec (Bcd | Ascii)
```

Value objects are immutable, monetary and numeric values are logical strings the field definitions validate, and enums carry intrinsic facts (which codec an encoding uses, how many digits a prefix has) but never business logic.

## Out of scope (v1)

Network transport (TCP framing, TLS), ISO 8583:2003, MAC generation, and PIN-block handling are deliberately excluded to keep the core focused and trustworthy. Proprietary field definitions are not bundled — they are expressed as catalogue overrides in your own code.

## Testing

```
composer test              # everything
composer test:unit         # unit suite
composer test:integration  # hand-derived wire fixtures
composer check             # lint + static analysis + tests
```

The integration suite asserts against wire fixtures assembled by hand from the spec, so the parser is proven against bytes the library did not itself produce.

## License

MIT.