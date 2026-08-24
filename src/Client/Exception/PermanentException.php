<?php

declare(strict_types=1);

namespace ClickTrail\Client\Exception;

/** Failure that will not succeed on retry (4xx validation, auth, schema). */
final class PermanentException extends \RuntimeException
{
}
