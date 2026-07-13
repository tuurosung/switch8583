<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Exceptions;

use Tuurosung\switch8583\Exceptions\Switch8583Exception;

/**
 * Thrown when raw input (binary or hex) cannot be interpreted as a
 * well-formed ISO 8583 structure — malformed hex, an inconsistent bitmap
 * length, a truncated length prefix, and so on.
 */
class ParseException extends \RuntimeException implements Switch8583Exception
{
}