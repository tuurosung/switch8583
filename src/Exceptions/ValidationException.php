<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Exceptions;

use InvalidArgumentException;
use Tuurosung\switch8583\Exceptions\Switch8583Exception;

/**
 * Thrown when a caller supplies an argument the library cannot accept —
 * for example a field number outside the valid range, or an attempt to set
 * a structural bit that the library manages automatically.
 */
class ValidationException extends InvalidArgumentException implements Switch8583Exception
{    
}