<?php

declare(strict_types= 1);

namespace Tuurosung\switch8583\Exceptions;

/**
 * Marker interface implemented by every exception this library throws.
 *
 * Consumers can catch {@see Switch8583Exception} to handle any failure originating
 * from the library, while each concrete exception still extends the most
 * appropriate SPL type (e.g. RuntimeException, InvalidArgumentException) so it
 * also behaves correctly for callers that catch those.
 */
interface Switch8583Exception extends \Throwable
{

}