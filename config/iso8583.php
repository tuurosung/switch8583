<?php

declare (strict_types = 1);

return [

    /*
    |--------------------------------------------------------------------------
    | Standard Version
    |--------------------------------------------------------------------------
    |
    | Which revision of ISO 8583 the resolved factory targets. Supported
    | values: "1987", "1993". Networks that deviate from the standard tables
    | can bind a customised version in a service provider instead — see the
    | package README on catalogue overrides via VersionInterface::withField().
    |
    */
    'version' => env('ISO8583_VERSION', '1987'),


    /*
    |--------------------------------------------------------------------------
    | MTI Encoding
    |--------------------------------------------------------------------------
    |
    | How the four-digit message type indicator is placed on the wire.
    | "ascii" writes four bytes; "bcd" packs it into two. Field encodings are
    | not set here — they come from the version catalogue per field.
    |
    */

    'mti_encoding' => env('ISO8583_MTI_ENCODING', 'ascii'),

];